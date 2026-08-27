<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternFormTextareaController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-textarea';
    }

    protected function page(): string
    {
        return 'UiPatternFormTextarea';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('form-textarea')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
