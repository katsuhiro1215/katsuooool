<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternChevronController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-chevron';
    }

    protected function page(): string
    {
        return 'PatternChevron';
    }
}
