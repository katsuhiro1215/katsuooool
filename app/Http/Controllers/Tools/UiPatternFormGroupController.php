<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternFormGroupController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-group';
    }

    protected function page(): string
    {
        return 'UiPatternFormGroup';
    }
}
