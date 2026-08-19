<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class SeoDescriptionCheckController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'seo-description-check';
    }

    protected function page(): string
    {
        return 'SeoDescriptionCheck';
    }
}
