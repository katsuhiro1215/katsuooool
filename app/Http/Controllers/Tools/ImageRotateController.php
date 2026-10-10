<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tools\ImageRotateService;
use App\Services\Tools\ToolDownloadService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\ToolUploadService;
use App\Support\ImageToolLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ImageRotateController extends Controller
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const DIRECTIONS = ['left', 'right', '180'];

    public function show(Request $request): Response
    {
        $user = $request->user();
        $isPremium = $user instanceof User && $user->isPremium();

        return Inertia::render('ImageRotate', [
            'maxFiles' => ImageToolLimits::maxFiles($isPremium),
        ]);
    }

    public function process(
        Request $request,
        ToolUploadService $uploadService,
        ImageRotateService $rotateService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $user = $request->user();
        $isPremium = $user instanceof User && $user->isPremium();
        $maxFileCount = ImageToolLimits::maxFiles($isPremium);

        $request->validate([
            'files' => ['required', 'array', 'max:'.$maxFileCount],
            'files.*' => ['file'],
            'direction' => ['required', 'string', 'in:'.implode(',', self::DIRECTIONS)],
        ]);

        $direction = $request->string('direction')->toString();

        $uploadedFiles = $request->file('files');
        $originalNames = array_map(
            fn ($file) => $file->getClientOriginalName(),
            $uploadedFiles,
        );

        $uploadResult = $uploadService->store(
            uploadedFiles: $uploadedFiles,
            allowedMimeTypes: self::ALLOWED_MIME_TYPES,
            maxFileBytes: ImageToolLimits::maxFileBytes(),
            maxFileCount: $maxFileCount,
        );

        $eventLogger->log(
            toolSlug: 'image-rotate',
            eventType: 'upload',
            data: ['file_count' => count($uploadResult->files)],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        $disk = Storage::disk('local');
        $results = [];
        $successfulFiles = [];
        $usedDownloadNames = [];
        $totalInputBytes = 0;
        $totalOutputBytes = 0;

        foreach ($uploadResult->files as $uploadedToolFile) {
            $inputAbsolutePath = $disk->path($uploadedToolFile->path);

            $disk->makeDirectory("tool-tmp/{$uploadResult->batchId}/rotated");

            try {
                $extension = match ($uploadedToolFile->mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => throw new RuntimeException("Unsupported mime type: {$uploadedToolFile->mimeType}"),
                };

                $dimensions = getimagesize($inputAbsolutePath);

                if ($dimensions === false) {
                    throw new RuntimeException('Failed to read image dimensions.');
                }

                if (ImageToolLimits::maxPixels() < $dimensions[0] * $dimensions[1]) {
                    $results[] = [
                        'original_name' => $uploadedToolFile->originalName,
                        'status' => 'failed',
                        'reason' => '画像の解像度が大きすぎます。',
                    ];

                    continue;
                }

                $outputRelativePath = "tool-tmp/{$uploadResult->batchId}/rotated/".basename($uploadedToolFile->path, '.'.pathinfo($uploadedToolFile->path, PATHINFO_EXTENSION)).'.'.$extension;

                $rotateService->rotate(
                    inputPath: $inputAbsolutePath,
                    outputPath: $disk->path($outputRelativePath),
                    mimeType: $uploadedToolFile->mimeType,
                    direction: $direction,
                );

                $outputDimensions = getimagesize($disk->path($outputRelativePath));

                if ($outputDimensions === false) {
                    throw new RuntimeException('Failed to read rotated image dimensions.');
                }

                $outputBytes = $disk->size($outputRelativePath);
            } catch (Throwable) {
                $results[] = [
                    'original_name' => $uploadedToolFile->originalName,
                    'status' => 'failed',
                    'reason' => '画像の回転に失敗しました。',
                ];

                continue;
            }

            $totalInputBytes += $uploadedToolFile->size;
            $totalOutputBytes += $outputBytes;

            $safeFilename = preg_replace('/[\\\\\/:*?"<>|]/', '_', pathinfo($uploadedToolFile->originalName, PATHINFO_FILENAME));
            $downloadName = $safeFilename.'.'.$extension;

            $suffix = 1;
            while (isset($usedDownloadNames[$downloadName])) {
                $downloadName = $safeFilename.'-'.$suffix.'.'.$extension;
                $suffix++;
            }

            $usedDownloadNames[$downloadName] = true;

            $successfulFiles[] = [
                'path' => $outputRelativePath,
                'download_name' => $downloadName,
            ];

            $results[] = [
                'original_name' => $uploadedToolFile->originalName,
                'status' => 'success',
                'width' => $outputDimensions[0],
                'height' => $outputDimensions[1],
                'download_url' => $downloadService->signedUrl($outputRelativePath, $downloadName),
            ];
        }

        $rejected = $uploadResult->rejected;
        ksort($rejected);

        foreach ($rejected as $index => $reason) {
            $results[] = [
                'original_name' => $originalNames[$index] ?? '不明なファイル',
                'status' => 'rejected',
                'reason' => $reason,
            ];
        }

        $batchDownloadUrl = null;

        if (count($successfulFiles) >= 2) {
            $zipRelativePath = $downloadService->zip(
                $uploadResult->batchId,
                $successfulFiles,
                'rotated-images.zip',
            );

            $batchDownloadUrl = $downloadService->signedUrl($zipRelativePath, 'rotated-images.zip');
        }

        $eventLogger->log(
            toolSlug: 'image-rotate',
            eventType: 'process',
            data: [
                'file_count' => count($successfulFiles),
                'input_bytes' => $totalInputBytes,
                'output_bytes' => $totalOutputBytes,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        $failedCount = count(array_filter($results, fn (array $result) => $result['status'] === 'failed'));

        if ($failedCount > 0) {
            $eventLogger->log(
                toolSlug: 'image-rotate',
                eventType: 'failed',
                data: ['file_count' => $failedCount],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $request->user()?->id,
            );
        }

        return response()->json([
            'results' => $results,
            'batch_download_url' => $batchDownloadUrl,
        ]);
    }
}
