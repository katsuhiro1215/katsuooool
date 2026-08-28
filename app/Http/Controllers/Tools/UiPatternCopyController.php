<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\UiPatternSnippet;
use App\Models\User;
use App\Services\CopyPopularityTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UiPatternCopyController extends Controller
{
    public function store(UiPatternSnippet $uiPatternSnippet, Request $request, CopyPopularityTracker $tracker): JsonResponse
    {
        $user = $request->user();
        $isPremiumViewer = $user instanceof User && $user->isPremium();

        abort_if($uiPatternSnippet->is_premium && ! $isPremiumViewer, 404);

        $tracker->record($uiPatternSnippet, $request->ip());

        return response()->json(['status' => 'ok']);
    }
}
