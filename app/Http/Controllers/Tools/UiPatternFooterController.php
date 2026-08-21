<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternFooterController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-footer';
    }

    protected function page(): string
    {
        return 'UiPatternFooter';
    }
}
