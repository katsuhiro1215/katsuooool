<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;
use Illuminate\Http\Request;

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
    protected function extraProps(Request $request): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('form-textarea')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
