<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssFlexboxController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-flexbox';
    }

    protected function page(): string
    {
        return 'CssFlexbox';
    }
}
