<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

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
}
