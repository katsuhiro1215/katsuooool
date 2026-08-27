<?php

namespace App\Http\Controllers\Tools\Concerns;

use App\Http\Controllers\Controller;
use App\Models\UiPatternSnippet;
use App\Models\User;
use App\Services\PatternDocumentRenderer;
use App\Services\Tools\ToolEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base for tool controllers whose calculation happens entirely in the
 * browser (Color and Web-text-processing categories) — no file upload or
 * download exists. `track()` exists solely so tool_events (and therefore
 * the Admin dashboard's per-tool execution counts) stay populated for
 * these tools too.
 */
abstract class TrackedToolController extends Controller
{
    abstract protected function slug(): string;

    abstract protected function page(): string;

    public function show(Request $request): Response
    {
        return Inertia::render($this->page(), $this->extraProps($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return [];
    }

    /**
     * Builds the `patterns` prop for a UI pattern gallery category, hiding
     * the raw html/css of premium-only patterns from non-premium viewers.
     *
     * @return array<string, mixed>
     */
    protected function patternGalleryProps(Request $request, string $category): array
    {
        $user = $request->user();
        $isPremiumViewer = $user instanceof User && $user->isPremium();
        $renderer = app(PatternDocumentRenderer::class);

        $patterns = UiPatternSnippet::query()
            ->forCategory($category)
            ->get(['id', 'title', 'html', 'css', 'is_premium'])
            ->map(function (UiPatternSnippet $pattern) use ($isPremiumViewer, $renderer): array {
                $locked = $pattern->is_premium && ! $isPremiumViewer;

                if ($locked) {
                    return [
                        'id' => $pattern->id,
                        'title' => $pattern->title,
                        'locked' => true,
                        'srcdoc' => $renderer->build($pattern->html, $pattern->css),
                    ];
                }

                return [
                    'id' => $pattern->id,
                    'title' => $pattern->title,
                    'locked' => false,
                    'html' => $pattern->html,
                    'css' => $pattern->css,
                ];
            });

        return ['patterns' => $patterns];
    }

    public function track(Request $request, ToolEventLogger $eventLogger): JsonResponse
    {
        $eventLogger->log(
            toolSlug: $this->slug(),
            eventType: 'process',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        return response()->json(['status' => 'ok']);
    }
}
