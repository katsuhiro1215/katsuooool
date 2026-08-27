<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

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

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('pagination')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
