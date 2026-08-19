<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class ColorPaletteController extends TrackedToolController
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
