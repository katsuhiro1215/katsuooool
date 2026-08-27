<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternFooterController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-footer';
    }

    protected function page(): string
    {
        return 'UiPatternFooter';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'footer');
    }
}
