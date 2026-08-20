<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternFormInputController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-input';
    }

    protected function page(): string
    {
        return 'UiPatternFormInput';
    }
}
