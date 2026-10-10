<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\WebsiteStarterSubmission;
use App\Services\Tools\FaviconGenerateService;
use App\Services\Tools\OgpImageService;
use App\Services\Tools\ToolDownloadService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\WebsiteStarterService;
use App\Support\ImageToolLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class WebsiteStarterController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('WebsiteStarter', [
            'industries' => config('website_starter.industries'),
            'prefectures' => config('website_starter.prefectures'),
            'targetAgeRanges' => config('website_starter.target_age_ranges'),
            'targetGenders' => config('website_starter.target_genders'),
            'purposes' => config('website_starter.purposes'),
        ]);
    }

    public function process(
        Request $request,
        FaviconGenerateService $faviconService,
        OgpImageService $ogpImageService,
        WebsiteStarterService $starterService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'site_url' => ['required', 'url', 'max:255'],
            'industry' => ['required', Rule::in(array_keys(config('website_starter.industries')))],
            'prefecture' => ['required', Rule::in(config('website_starter.prefectures'))],
            'target_age_range' => ['nullable', Rule::in(array_keys(config('website_starter.target_age_ranges')))],
            'target_gender' => ['nullable', Rule::in(array_keys(config('website_starter.target_genders')))],
            'purpose' => ['nullable', Rule::in(array_keys(config('website_starter.purposes')))],
            'logo' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.(ImageToolLimits::maxFileBytes() / 1024),
            ],
            'meta_description' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        WebsiteStarterSubmission::create([
            'user_id' => $user->id,
            'industry' => $validated['industry'],
            'prefecture' => $validated['prefecture'],
            'target_age_range' => $validated['target_age_range'] ?? null,
            'target_gender' => $validated['target_gender'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
        ]);

        $logo = $request->file('logo');
        $batchId = (string) Str::uuid();
        $disk = Storage::disk('local');

        $disk->makeDirectory("tool-tmp/{$batchId}/source");
        $disk->makeDirectory("tool-tmp/{$batchId}/favicons");
        $disk->makeDirectory("tool-tmp/{$batchId}/ogp");

        $extension = $logo->guessExtension() ?? $logo->getClientOriginalExtension();
        $sourceStoredName = Str::uuid().'.'.$extension;
        $sourceRelativePath = "tool-tmp/{$batchId}/source/{$sourceStoredName}";

        $disk->putFileAs("tool-tmp/{$batchId}/source", $logo, $sourceStoredName);

        $eventLogger->log(
            toolSlug: 'website-starter',
            eventType: 'upload',
            data: ['file_count' => 1],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $user->id,
        );

        try {
            $dimensions = getimagesize($disk->path($sourceRelativePath));

            if ($dimensions === false) {
                throw new RuntimeException('Failed to read image dimensions.');
            }

            if (ImageToolLimits::maxPixels() < $dimensions[0] * $dimensions[1]) {
                $eventLogger->log(
                    toolSlug: 'website-starter',
                    eventType: 'failed',
                    data: ['file_count' => 1],
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                    userId: $user->id,
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

            $faviconZipRelativePath = $downloadService->zip($batchId, $zipEntries, 'favicon.zip');
            $faviconDownloadUrl = $downloadService->signedUrl($faviconZipRelativePath, 'favicon.zip');

            $ogpMimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                default => 'image/png',
            };
            $ogpExtension = match ($ogpMimeType) {
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default => 'png',
            };
            $ogpRelativePath = "tool-tmp/{$batchId}/ogp/ogp-image.{$ogpExtension}";

            $ogpImageService->generate(
                inputPath: $disk->path($sourceRelativePath),
                outputPath: $disk->path($ogpRelativePath),
                mimeType: $ogpMimeType,
            );

            $ogpDownloadUrl = $downloadService->signedUrl($ogpRelativePath, "ogp-image.{$ogpExtension}");
        } catch (Throwable) {
            $eventLogger->log(
                toolSlug: 'website-starter',
                eventType: 'failed',
                data: ['file_count' => 1],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $user->id,
            );

            return response()->json([
                'status' => 'failed',
                'reason' => '素材の生成に失敗しました。',
            ]);
        }

        $industryLabel = config('website_starter.industries')[$validated['industry']];
        $title = $starterService->buildTitle($validated['site_name'], $industryLabel, $validated['prefecture']);
        $metaTags = $starterService->buildMetaTagsBlock($title, $validated['meta_description'], $validated['site_url']);
        $htmlHead = $starterService->buildHtmlHeadBlock($title, $validated['meta_description'], $validated['site_url']);

        $eventLogger->log(
            toolSlug: 'website-starter',
            eventType: 'process',
            data: ['file_count' => 1, 'input_bytes' => $logo->getSize()],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $user->id,
        );

        return response()->json([
            'status' => 'success',
            'title' => $title,
            'favicon_download_url' => $faviconDownloadUrl,
            'ogp_image_download_url' => $ogpDownloadUrl,
            'meta_tags' => $metaTags,
            'html_head' => $htmlHead,
        ]);
    }
}
