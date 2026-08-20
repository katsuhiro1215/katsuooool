<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

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
}
