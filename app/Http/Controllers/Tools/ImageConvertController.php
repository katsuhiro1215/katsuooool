<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tools\ImageConvertService;
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
use RuntimeException;
use Throwable;

class ImageConvertController extends Controller
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'];

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
            'maxFiles' => ImageToolLimits::maxFiles($isPremium),
            'avifMaxFiles' => ImageToolLimits::avifMaxFiles($isPremium),
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

        $request->validate([
            'format' => ['required', 'string', 'in:'.implode(',', array_keys(self::TARGET_FORMATS))],
        ]);

        $targetExtension = $request->string('format')->toString();
        $targetMimeType = self::TARGET_FORMATS[$targetExtension];

        // AVIF への変換は負荷が高いため、枚数を絞る
        $maxFileCount = $targetExtension === 'avif'
            ? ImageToolLimits::avifMaxFiles($isPremium)
            : ImageToolLimits::maxFiles($isPremium);

        $request->validate([
            'files' => ['required', 'array', 'max:'.$maxFileCount],
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
                $dimensions = $this->readDimensions($inputAbsolutePath);

                if ($dimensions === null) {
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

                $outputRelativePath = "tool-tmp/{$uploadResult->batchId}/converted/".basename($uploadedToolFile->path, '.'.pathinfo($uploadedToolFile->path, PATHINFO_EXTENSION)).'.'.$targetExtension;

                $outputBytes = $convertService->convert(
                    inputPath: $inputAbsolutePath,
                    outputPath: $disk->path($outputRelativePath),
                    targetMimeType: $targetMimeType,
                );
            } catch (Throwable $exception) {
                Log::warning('Image conversion failed', [
                    'target_mime_type' => $targetMimeType,
                    'original_name' => $uploadedToolFile->originalName,
                    'exception' => $exception->getMessage(),
                ]);

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
                'download_name' => $downloadName,
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

    /**
     * @return array{0: int, 1: int}|null
     */
    private function readDimensions(string $path): ?array
    {
        $size = getimagesize($path);

        if ($size !== false) {
            return [$size[0], $size[1]];
        }

        // HEIC など getimagesize が読めない形式は、Imagick のヘッダー読み取りで調べる（デコードしない）
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $imagick = new \Imagick;
            $imagick->pingImage($path);
            $dimensions = [$imagick->getImageWidth(), $imagick->getImageHeight()];
            $imagick->clear();

            return $dimensions;
        } catch (\ImagickException) {
            return null;
        }
    }
}
