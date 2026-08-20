<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternDotController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-dot';
    }

    protected function page(): string
    {
        return 'PatternDot';
    }
}
