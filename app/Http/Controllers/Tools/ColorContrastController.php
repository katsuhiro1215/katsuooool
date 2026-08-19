<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class ColorContrastController extends TrackedToolController
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
