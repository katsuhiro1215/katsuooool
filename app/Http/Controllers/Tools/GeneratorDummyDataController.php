<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class GeneratorDummyDataController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'generator-dummy-data';
    }

    protected function page(): string
    {
        return 'GeneratorDummyData';
    }
}
