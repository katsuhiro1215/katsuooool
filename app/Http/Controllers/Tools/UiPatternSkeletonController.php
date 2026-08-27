<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

class UiPatternSkeletonController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-skeleton';
    }

    protected function page(): string
    {
        return 'UiPatternSkeleton';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('skeleton')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
