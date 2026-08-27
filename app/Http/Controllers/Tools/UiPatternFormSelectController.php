<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternFormSelectController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-form-select';
    }

    protected function page(): string
    {
        return 'UiPatternFormSelect';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('form-select')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
