<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tools\ImageConvertService;
use App\Services\Tools\ToolDownloadService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\ToolUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ImageConvertController extends Controller
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_FILE_COUNT = 10;

    private const FREE_MAX_FILE_COUNT = 3;

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    private const MAX_PIXELS = 25_000_000;

    private const TARGET_FORMATS = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    public function show(Request $request): Response
    {
        $user = $request->user();
        $isPremium = $user instanceof User && $user->isPremium();

        return Inertia::render('ImageConvert', [
            'maxFiles' => $isPremium ? self::MAX_FILE_COUNT : self::FREE_MAX_FILE_COUNT,
        ]);
    }

    public function process(
        Request $request,
        ToolUploadService $uploadService,
        ImageConvertService $convertService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $user = $request->user();
        $isPremium = $user instanceof User && $user->isPremium();
        $maxFileCount = $isPremium ? self::MAX_FILE_COUNT : self::FREE_MAX_FILE_COUNT;

        $request->validate([
            'files' => ['required', 'array', 'max:'.$maxFileCount],
            'files.*' => ['file'],
            'format' => ['required', 'string', 'in:'.implode(',', array_keys(self::TARGET_FORMATS))],
        ]);

        $targetExtension = $request->string('format')->toString();
        $targetMimeType = self::TARGET_FORMATS[$targetExtension];

        $uploadedFiles = $request->file('files');
        $originalNames = array_map(
            fn ($file) => $file->getClientOriginalName(),
            $uploadedFiles,
        );

        $uploadResult = $uploadService->store(
            uploadedFiles: $uploadedFiles,
            allowedMimeTypes: self::ALLOWED_MIME_TYPES,
            maxFileBytes: self::MAX_FILE_BYTES,
            maxFileCount: $maxFileCount,
        );

        $eventLogger->log(
            toolSlug: 'image-convert',
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

            $disk->makeDirectory("tool-tmp/{$uploadResult->batchId}/converted");

            try {
                $dimensions = getimagesize($inputAbsolutePath);

                if ($dimensions === false) {
                    throw new RuntimeException('Failed to read image dimensions.');
                }

                if ($dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
                    $results[] = [
                        'original_name' => $uploadedToolFile->originalName,
                        'status' => 'failed',
                        'reason' => '画像の解像度が大きすぎます。',
                    ];

                    continue;
                }

                $outputRelativePath = "tool-tmp/{$uploadResult->batchId}/converted/".basename($uploadedToolFile->path, '.'.pathinfo($uploadedToolFile->path, PATHINFO_EXTENSION)).'.'.$targetExtension;

                $outputBytes = $convertService->convert(
                    inputPath: $inputAbsolutePath,
                    outputPath: $disk->path($outputRelativePath),
                    targetMimeType: $targetMimeType,
                );
            } catch (Throwable) {
                $results[] = [
                    'original_name' => $uploadedToolFile->originalName,
                    'status' => 'failed',
                    'reason' => '画像の変換に失敗しました。',
                ];

                continue;
            }

            $totalInputBytes += $uploadedToolFile->size;
            $totalOutputBytes += $outputBytes;

            $safeFilename = preg_replace('/[\\\\\/:*?"<>|]/', '_', pathinfo($uploadedToolFile->originalName, PATHINFO_FILENAME));
            $downloadName = $safeFilename.'.'.$targetExtension;

            $suffix = 1;
            while (isset($usedDownloadNames[$downloadName])) {
                $downloadName = $safeFilename.'-'.$suffix.'.'.$targetExtension;
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
                'input_bytes' => $uploadedToolFile->size,
                'output_bytes' => $outputBytes,
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
                'converted-images.zip',
            );

            $batchDownloadUrl = $downloadService->signedUrl($zipRelativePath, 'converted-images.zip');
        }

        $eventLogger->log(
            toolSlug: 'image-convert',
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
                toolSlug: 'image-convert',
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
