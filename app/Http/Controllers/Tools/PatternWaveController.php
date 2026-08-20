<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternWaveController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-wave';
    }

    protected function page(): string
    {
        return 'PatternWave';
    }
}
