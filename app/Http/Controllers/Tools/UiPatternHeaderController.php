<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternHeaderController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-header';
    }

    protected function page(): string
    {
        return 'UiPatternHeader';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'header');
    }
}
