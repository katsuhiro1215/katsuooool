<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UrlEncodeController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'url-encode';
    }

    protected function page(): string
    {
        return 'UrlEncode';
    }
}
