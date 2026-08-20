<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternGridController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-grid';
    }

    protected function page(): string
    {
        return 'PatternGrid';
    }
}
