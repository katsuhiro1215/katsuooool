<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssGlassmorphismController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-glassmorphism';
    }

    protected function page(): string
    {
        return 'CssGlassmorphism';
    }
}
