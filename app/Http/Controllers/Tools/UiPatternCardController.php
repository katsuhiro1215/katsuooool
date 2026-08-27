<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternCardController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-card';
    }

    protected function page(): string
    {
        return 'UiPatternCard';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('card')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
