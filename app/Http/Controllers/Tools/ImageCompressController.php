<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tools\ImageCompressService;
use App\Services\Tools\ToolDownloadService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\ToolUploadService;
use App\Support\ImageToolLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImageCompressController extends Controller
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function show(Request $request): Response
    {
        $user = $request->user();
        $isPremium = $user instanceof User && $user->isPremium();

        return Inertia::render('ImageCompress', [
            'maxFiles' => ImageToolLimits::maxFiles($isPremium),
        ]);
    }

    public function process(
        Request $request,
        ToolUploadService $uploadService,
        ImageCompressService $compressService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $user = $request->user();
        $isPremium = $user instanceof User && $user->isPremium();
        $maxFileCount = ImageToolLimits::maxFiles($isPremium);

        $request->validate([
            'files' => ['required', 'array', 'max:'.$maxFileCount],
            'level' => ['sometimes', 'in:light,recommended,strong'],
            'files.*' => ['file'],
        ]);

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
            toolSlug: 'image-compress',
            eventType: 'upload',
            data: ['file_count' => count($uploadResult->files)],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        $disk = Storage::disk('local');
        $results = [];
        $successfulFiles = [];
        $totalInputBytes = 0;
        $totalOutputBytes = 0;
        $usedDownloadNames = [];

        // Note: $uploadResult->files is a sequentially re-indexed array (only
        // accepted files, in original order) — its keys do NOT correspond to
        // the original $uploadedFiles indices. $uploadResult->rejected keys
        // DO correspond to the original indices. These two index spaces must
        // never be mixed into the same $results array via array-key
        // assignment; append with $results[] instead, in each loop's own
        // natural order (successes first, then rejects).
        foreach ($uploadResult->files as $uploadedToolFile) {
            $inputAbsolutePath = $disk->path($uploadedToolFile->path);

            $disk->makeDirectory("tool-tmp/{$uploadResult->batchId}/compressed");

            try {
                $extension = match ($uploadedToolFile->mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => throw new InvalidArgumentException("Unsupported mime type: {$uploadedToolFile->mimeType}"),
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

                $outputRelativePath = "tool-tmp/{$uploadResult->batchId}/compressed/".basename($uploadedToolFile->path, '.'.pathinfo($uploadedToolFile->path, PATHINFO_EXTENSION)).'.'.$extension;

                $outputBytes = $compressService->compress(
                    inputPath: $inputAbsolutePath,
                    outputPath: $disk->path($outputRelativePath),
                    mimeType: $uploadedToolFile->mimeType,
                    level: $request->string('level', config('image_tools.compression.default_level'))->toString(),
                );

                if ($outputBytes >= $uploadedToolFile->size) {
                    $disk->copy($uploadedToolFile->path, $outputRelativePath);
                    $outputBytes = $uploadedToolFile->size;
                }
            } catch (Throwable $exception) {
                Log::warning('Image compression failed', [
                    'mime_type' => $uploadedToolFile->mimeType,
                    'original_name' => $uploadedToolFile->originalName,
                    'exception' => $exception->getMessage(),
                ]);

                $results[] = [
                    'original_name' => $uploadedToolFile->originalName,
                    'status' => 'failed',
                    'reason' => '画像の処理に失敗しました。',
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
                'input_bytes' => $uploadedToolFile->size,
                'output_bytes' => $outputBytes,
                'reduction_percent' => $uploadedToolFile->size > 0
                    ? (int) round((1 - ($outputBytes / $uploadedToolFile->size)) * 100)
                    : 0,
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
                'compressed-images.zip',
            );

            $batchDownloadUrl = $downloadService->signedUrl($zipRelativePath, 'compressed-images.zip');
        }

        $eventLogger->log(
            toolSlug: 'image-compress',
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
                toolSlug: 'image-compress',
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
