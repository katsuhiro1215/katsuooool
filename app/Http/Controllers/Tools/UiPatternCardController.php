<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternCardController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-card';
    }

    protected function page(): string
    {
        return 'UiPatternCard';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'card');
    }
}
