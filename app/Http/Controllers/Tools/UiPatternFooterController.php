<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternFooterController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-footer';
    }

    protected function page(): string
    {
        return 'UiPatternFooter';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('footer')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
