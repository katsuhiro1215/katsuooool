<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class JsonFormatController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'json-format';
    }

    protected function page(): string
    {
        return 'JsonFormat';
    }
}
