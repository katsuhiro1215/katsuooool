<?php

namespace App\Http\Controllers\Tools\Concerns;

use App\Http\Controllers\Controller;
use App\Services\Tools\ToolEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base for Color-category tool controllers. Unlike the Image tools, these
 * never receive an uploaded file — the calculation happens entirely in the
 * browser. `track()` exists solely so tool_events (and therefore the Admin
 * dashboard's per-tool execution counts) stay populated for these tools too.
 */
abstract class ColorToolController extends Controller
{
    abstract protected function slug(): string;

    abstract protected function page(): string;

    public function show(): Response
    {
        return Inertia::render($this->page());
    }

    public function track(Request $request, ToolEventLogger $eventLogger): JsonResponse
    {
        $eventLogger->log(
            toolSlug: $this->slug(),
            eventType: 'process',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['status' => 'ok']);
    }
}
