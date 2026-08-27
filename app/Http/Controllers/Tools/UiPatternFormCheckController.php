<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternFormCheckController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-check';
    }

    protected function page(): string
    {
        return 'UiPatternFormCheck';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('form-check')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
