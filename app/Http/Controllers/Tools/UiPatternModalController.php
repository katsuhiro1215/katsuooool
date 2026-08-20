<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternModalController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-modal';
    }

    protected function page(): string
    {
        return 'UiPatternModal';
    }
}
