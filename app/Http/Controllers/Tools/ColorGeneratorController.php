<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class ColorGeneratorController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'color-generator';
    }

    protected function page(): string
    {
        return 'ColorGenerator';
    }
}
