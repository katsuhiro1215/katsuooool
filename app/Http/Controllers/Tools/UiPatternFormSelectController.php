<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternFormSelectController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-select';
    }

    protected function page(): string
    {
        return 'UiPatternFormSelect';
    }
}
