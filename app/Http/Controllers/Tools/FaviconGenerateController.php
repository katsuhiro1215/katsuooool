<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\FaviconGenerateService;
use App\Services\Tools\ToolDownloadService;
use App\Services\Tools\ToolEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class FaviconGenerateController extends Controller
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_PIXELS = 25_000_000;

    public function show(): Response
    {
        return Inertia::render('FaviconGenerate');
    }

    public function process(
        Request $request,
        FaviconGenerateService $faviconService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.(self::MAX_FILE_BYTES / 1024),
            ],
        ]);

        $uploadedFile = $request->file('file');
        $batchId = (string) Str::uuid();
        $disk = Storage::disk('local');

        $disk->makeDirectory("tool-tmp/{$batchId}/source");
        $disk->makeDirectory("tool-tmp/{$batchId}/favicons");

        $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension();
        $sourceStoredName = Str::uuid().'.'.$extension;
        $sourceRelativePath = "tool-tmp/{$batchId}/source/{$sourceStoredName}";

        $disk->putFileAs("tool-tmp/{$batchId}/source", $uploadedFile, $sourceStoredName);

        $eventLogger->log(
            toolSlug: 'favicon-generate',
            eventType: 'upload',
            data: ['file_count' => 1],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        try {
            $dimensions = getimagesize($disk->path($sourceRelativePath));

            if ($dimensions === false) {
                throw new RuntimeException('Failed to read image dimensions.');
            }

            if ($dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
                $eventLogger->log(
                    toolSlug: 'favicon-generate',
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

            $favicons = $faviconService->generate(
                inputPath: $disk->path($sourceRelativePath),
                outputDir: $disk->path("tool-tmp/{$batchId}/favicons"),
            );

            $zipEntries = array_map(
                fn (array $favicon) => [
                    'path' => "tool-tmp/{$batchId}/favicons/{$favicon['filename']}",
                    'download_name' => $favicon['filename'],
                ],
                $favicons,
            );

            $zipRelativePath = $downloadService->zip($batchId, $zipEntries, 'favicon.zip');
            $downloadUrl = $downloadService->signedUrl($zipRelativePath, 'favicon.zip');
        } catch (Throwable) {
            $eventLogger->log(
                toolSlug: 'favicon-generate',
                eventType: 'failed',
                data: ['file_count' => 1],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $request->user()?->id,
            );

            return response()->json([
                'status' => 'failed',
                'reason' => 'faviconの生成に失敗しました。',
            ]);
        }

        $eventLogger->log(
            toolSlug: 'favicon-generate',
            eventType: 'process',
            data: [
                'file_count' => 1,
                'input_bytes' => $uploadedFile->getSize(),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        return response()->json([
            'status' => 'success',
            'download_url' => $downloadUrl,
        ]);
    }
}
