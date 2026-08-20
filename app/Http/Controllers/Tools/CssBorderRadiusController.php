<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssBorderRadiusController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-border-radius';
    }

    protected function page(): string
    {
        return 'CssBorderRadius';
    }
}
