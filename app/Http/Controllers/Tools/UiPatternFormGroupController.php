<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternFormGroupController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-group';
    }

    protected function page(): string
    {
        return 'UiPatternFormGroup';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'form-group');
    }
}
