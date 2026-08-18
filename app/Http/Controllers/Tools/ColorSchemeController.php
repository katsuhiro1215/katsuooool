<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorToolController;

class ColorSchemeController extends ColorToolController
{
    protected function slug(): string
    {
        return 'color-scheme';
    }

    protected function page(): string
    {
        return 'ColorScheme';
    }
}
