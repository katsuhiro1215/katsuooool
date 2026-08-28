<?php

namespace App\Http\Controllers\Tools\Concerns;

use App\Models\ColorExample;
use App\Services\CopyPopularityTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ColorExampleCopyable
{
    abstract protected function exampleType(): string;

    public function copyExample(ColorExample $colorExample, Request $request, CopyPopularityTracker $tracker): JsonResponse
    {
        abort_unless($colorExample->type === $this->exampleType(), 404);

        $tracker->record($colorExample, $request->ip());

        return response()->json(['status' => 'ok']);
    }
}
