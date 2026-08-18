<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorToolController;

class ColorGradientController extends ColorToolController
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
