<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class ColorGradientController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'color-gradient';
    }

    protected function page(): string
    {
        return 'ColorGradient';
    }
}
