<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

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
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'toast');
    }
}
