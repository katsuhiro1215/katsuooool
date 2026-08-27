<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternFormTextareaController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-textarea';
    }

    protected function page(): string
    {
        return 'UiPatternFormTextarea';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'form-textarea');
    }
}
