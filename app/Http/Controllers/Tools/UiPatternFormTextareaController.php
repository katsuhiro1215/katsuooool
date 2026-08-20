<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternFormTextareaController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-textarea';
    }

    protected function page(): string
    {
        return 'UiPatternFormTextarea';
    }
}
