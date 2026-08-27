<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternFormInputController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-input';
    }

    protected function page(): string
    {
        return 'UiPatternFormInput';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('form-input')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
