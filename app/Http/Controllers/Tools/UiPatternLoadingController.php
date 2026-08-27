<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternLoadingController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-loading';
    }

    protected function page(): string
    {
        return 'UiPatternLoading';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('loading')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
