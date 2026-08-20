<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternCheckerboardController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-checkerboard';
    }

    protected function page(): string
    {
        return 'PatternCheckerboard';
    }
}
