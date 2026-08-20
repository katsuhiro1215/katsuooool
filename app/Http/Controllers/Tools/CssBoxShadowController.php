<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssBoxShadowController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-box-shadow';
    }

    protected function page(): string
    {
        return 'CssBoxShadow';
    }
}
