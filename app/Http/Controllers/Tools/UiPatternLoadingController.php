<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternLoadingController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-loading';
    }

    protected function page(): string
    {
        return 'UiPatternLoading';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'loading');
    }
}
