<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;
use Illuminate\Http\Request;

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
    protected function extraProps(Request $request): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('form-input')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
