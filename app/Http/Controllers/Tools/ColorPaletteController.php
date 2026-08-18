<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorToolController;

class ColorPaletteController extends ColorToolController
{
    protected function slug(): string
    {
        return 'color-palette';
    }

    protected function page(): string
    {
        return 'ColorPalette';
    }
}
