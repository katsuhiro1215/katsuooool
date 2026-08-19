<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class Base64EncodeController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'base64-encode';
    }

    protected function page(): string
    {
        return 'Base64Encode';
    }
}
