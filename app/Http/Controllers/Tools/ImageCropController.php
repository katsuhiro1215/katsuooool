<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\ImageCropService;
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

class ImageCropController extends Controller
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_PIXELS = 25_000_000;

    public function show(): Response
    {
        return Inertia::render('ImageCrop');
    }

    public function process(
        Request $request,
        ToolUploadService $uploadService,
        ImageCropService $cropService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $request->validate([
            'file' => ['required', 'file'],
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'width' => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],
        ]);

        $x = $request->integer('x');
        $y = $request->integer('y');
        $width = $request->integer('width');
        $height = $request->integer('height');

        $uploadResult = $uploadService->store(
            uploadedFiles: [$request->file('file')],
            allowedMimeTypes: self::ALLOWED_MIME_TYPES,
            maxFileBytes: self::MAX_FILE_BYTES,
            maxFileCount: 1,
        );

        $eventLogger->log(
            toolSlug: 'image-crop',
            eventType: 'upload',
            data: ['file_count' => count($uploadResult->files)],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        if (count($uploadResult->files) === 0) {
            $reason = $uploadResult->rejected[0] ?? '対応していないファイル形式です。';

            return response()->json([
                'status' => 'rejected',
                'reason' => $reason,
            ]);
        }

        $uploadedToolFile = $uploadResult->files[0];
        $disk = Storage::disk('local');
        $inputAbsolutePath = $disk->path($uploadedToolFile->path);

        $dimensions = getimagesize($inputAbsolutePath);

        if ($dimensions === false) {
            $eventLogger->log(
                toolSlug: 'image-crop',
                eventType: 'failed',
                data: ['file_count' => 1],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $request->user()?->id,
            );

            return response()->json([
                'status' => 'failed',
                'reason' => '画像の読み込みに失敗しました。',
            ]);
        }

        if ($dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
            $eventLogger->log(
                toolSlug: 'image-crop',
                eventType: 'failed',
                data: ['file_count' => 1],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $request->user()?->id,
            );

            return response()->json([
                'status' => 'failed',
                'reason' => '画像の解像度が大きすぎます。',
            ]);
        }

        if ($x + $width > $dimensions[0] || $y + $height > $dimensions[1]) {
            $eventLogger->log(
                toolSlug: 'image-crop',
                eventType: 'failed',
                data: ['file_count' => 1],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $request->user()?->id,
            );

            return response()->json([
                'status' => 'failed',
                'reason' => '切り抜き範囲が画像のサイズを超えています。',
            ]);
        }

        $disk->makeDirectory("tool-tmp/{$uploadResult->batchId}/cropped");

        try {
            $extension = match ($uploadedToolFile->mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => throw new RuntimeException("Unsupported mime type: {$uploadedToolFile->mimeType}"),
            };

            $outputRelativePath = "tool-tmp/{$uploadResult->batchId}/cropped/".basename($uploadedToolFile->path, '.'.pathinfo($uploadedToolFile->path, PATHINFO_EXTENSION)).'.'.$extension;

            $outputBytes = $cropService->crop(
                inputPath: $inputAbsolutePath,
                outputPath: $disk->path($outputRelativePath),
                mimeType: $uploadedToolFile->mimeType,
                x: $x,
                y: $y,
                width: $width,
                height: $height,
            );
        } catch (Throwable) {
            $eventLogger->log(
                toolSlug: 'image-crop',
                eventType: 'failed',
                data: ['file_count' => 1],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $request->user()?->id,
            );

            return response()->json([
                'status' => 'failed',
                'reason' => '画像の切り抜きに失敗しました。',
            ]);
        }

        $safeFilename = preg_replace('/[\\\\\/:*?"<>|]/', '_', pathinfo($uploadedToolFile->originalName, PATHINFO_FILENAME));
        $downloadName = $safeFilename.'-cropped.'.$extension;

        $eventLogger->log(
            toolSlug: 'image-crop',
            eventType: 'process',
            data: [
                'input_bytes' => $uploadedToolFile->size,
                'output_bytes' => $outputBytes,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        return response()->json([
            'status' => 'success',
            'width' => $width,
            'height' => $height,
            'download_url' => $downloadService->signedUrl($outputRelativePath, $downloadName),
        ]);
    }
}
