<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class HearingSheetController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'hearing-sheet';
    }

    protected function page(): string
    {
        return 'HearingSheet';
    }
}
