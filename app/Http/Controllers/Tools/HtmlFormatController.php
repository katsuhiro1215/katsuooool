<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class HtmlFormatController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'html-format';
    }

    protected function page(): string
    {
        return 'HtmlFormat';
    }
}
