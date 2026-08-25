<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternButtonController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-button';
    }

    protected function page(): string
    {
        return 'UiPatternButton';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('button')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
