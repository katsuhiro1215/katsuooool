<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternFormCheckController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-check';
    }

    protected function page(): string
    {
        return 'UiPatternFormCheck';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'form-check');
    }
}
