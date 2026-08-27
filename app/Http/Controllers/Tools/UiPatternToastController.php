<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternToastController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-toast';
    }

    protected function page(): string
    {
        return 'UiPatternToast';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('toast')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
