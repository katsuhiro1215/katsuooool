<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternFormCheckController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-check';
    }

    protected function page(): string
    {
        return 'UiPatternFormCheck';
    }
}
