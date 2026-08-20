<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

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
}
