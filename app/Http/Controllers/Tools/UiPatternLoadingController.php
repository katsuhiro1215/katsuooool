<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

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
}
