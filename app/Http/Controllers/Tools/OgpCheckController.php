<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\OgpCheckService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\UrlFetchException;
use App\Services\Tools\UrlFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OgpCheckController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('OgpCheck');
    }

    public function check(
        Request $request,
        UrlFetchService $urlFetchService,
        OgpCheckService $ogpCheckService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        try {
            $html = $urlFetchService->fetch($request->string('url')->toString());
        } catch (UrlFetchException $e) {
            $eventLogger->log(
                toolSlug: 'ogp-check',
                eventType: 'failed',
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $request->user()?->id,
            );

            return response()->json([
                'status' => 'failed',
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ], 422);
        }

        $analysis = $ogpCheckService->analyze($html);

        $eventLogger->log(
            toolSlug: 'ogp-check',
            eventType: 'process',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        return response()->json(array_merge(['status' => 'success'], $analysis));
    }
}
