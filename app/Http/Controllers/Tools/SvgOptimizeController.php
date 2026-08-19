<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class SvgOptimizeController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'svg-optimize';
    }

    protected function page(): string
    {
        return 'SvgOptimize';
    }
}
