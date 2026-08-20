<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternToastController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-toast';
    }

    protected function page(): string
    {
        return 'UiPatternToast';
    }
}
