<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

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
}
