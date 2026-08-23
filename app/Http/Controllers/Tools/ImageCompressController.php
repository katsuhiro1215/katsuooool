<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\ImageCompressService;
use App\Services\Tools\ToolDownloadService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\ToolUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImageCompressController extends Controller
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_FILE_COUNT = 10;

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_PIXELS = 25_000_000;

    public function show(): Response
    {
        return Inertia::render('ImageCompress');
    }

    public function process(
        Request $request,
        ToolUploadService $uploadService,
        ImageCompressService $compressService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $request->validate([
            'files' => ['required', 'array', 'max:'.self::MAX_FILE_COUNT],
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
            maxFileBytes: self::MAX_FILE_BYTES,
            maxFileCount: self::MAX_FILE_COUNT,
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

                if ($dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
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
                );

                if ($outputBytes >= $uploadedToolFile->size) {
                    $disk->copy($uploadedToolFile->path, $outputRelativePath);
                    $outputBytes = $uploadedToolFile->size;
                }
            } catch (Throwable) {
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
