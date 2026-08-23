<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\StructuredDataCheckService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\UrlFetchException;
use App\Services\Tools\UrlFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StructuredDataCheckController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('StructuredDataCheck');
    }

    public function check(
        Request $request,
        UrlFetchService $urlFetchService,
        StructuredDataCheckService $structuredDataCheckService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        try {
            $html = $urlFetchService->fetch($request->string('url')->toString());
        } catch (UrlFetchException $e) {
            $eventLogger->log(
                toolSlug: 'structured-data-check',
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

        $blocks = $structuredDataCheckService->analyze($html);

        $eventLogger->log(
            toolSlug: 'structured-data-check',
            eventType: 'process',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        return response()->json(['status' => 'success', 'blocks' => $blocks]);
    }
}
