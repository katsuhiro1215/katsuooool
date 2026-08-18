<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorToolController;

class ColorConvertController extends ColorToolController
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
