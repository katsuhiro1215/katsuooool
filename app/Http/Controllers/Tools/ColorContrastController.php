<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorToolController;

class ColorContrastController extends ColorToolController
{
    protected function slug(): string
    {
        return 'color-contrast';
    }

    protected function page(): string
    {
        return 'ColorContrast';
    }
}
