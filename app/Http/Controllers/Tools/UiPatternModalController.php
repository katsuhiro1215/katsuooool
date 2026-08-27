<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;
use Illuminate\Http\Request;

class UiPatternModalController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-modal';
    }

    protected function page(): string
    {
        return 'UiPatternModal';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('modal')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
