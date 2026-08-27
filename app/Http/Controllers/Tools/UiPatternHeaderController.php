<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternHeaderController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-header';
    }

    protected function page(): string
    {
        return 'UiPatternHeader';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('header')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
