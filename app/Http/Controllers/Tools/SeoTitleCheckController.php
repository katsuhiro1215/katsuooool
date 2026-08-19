<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class SeoTitleCheckController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'seo-title-check';
    }

    protected function page(): string
    {
        return 'SeoTitleCheck';
    }
}
