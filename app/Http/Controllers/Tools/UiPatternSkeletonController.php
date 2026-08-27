<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternSkeletonController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-skeleton';
    }

    protected function page(): string
    {
        return 'UiPatternSkeleton';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'skeleton');
    }
}
