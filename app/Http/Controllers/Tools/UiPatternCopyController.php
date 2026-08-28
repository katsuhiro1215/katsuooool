<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\UiPatternSnippet;
use App\Services\CopyPopularityTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UiPatternCopyController extends Controller
{
    public function store(UiPatternSnippet $uiPatternSnippet, Request $request, CopyPopularityTracker $tracker): JsonResponse
    {
        $tracker->record($uiPatternSnippet, $request->ip());

        return response()->json(['status' => 'ok']);
    }
}
