<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class UiPatternPaginationController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-pagination';
    }

    protected function page(): string
    {
        return 'UiPatternPagination';
    }
}
