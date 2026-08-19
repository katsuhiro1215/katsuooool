<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class ColorConvertController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'color-convert';
    }

    protected function page(): string
    {
        return 'ColorConvert';
    }
}
