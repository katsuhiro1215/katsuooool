<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssGridController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-grid';
    }

    protected function page(): string
    {
        return 'CssGrid';
    }
}
