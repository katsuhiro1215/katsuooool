<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternButtonController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-button';
    }

    protected function page(): string
    {
        return 'UiPatternButton';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'button');
    }
}
