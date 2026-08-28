<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorExampleCopyable;
use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\ColorExample;
use Illuminate\Http\Request;

class ColorPaletteController extends TrackedToolController
{
    use ColorExampleCopyable;

    protected function slug(): string
    {
        return 'color-palette';
    }

    protected function page(): string
    {
        return 'ColorPalette';
    }

    protected function exampleType(): string
    {
        return 'palette';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return [
            'examples' => ColorExample::ofType('palette')->limit(24)->get(['id', 'title', 'colors', 'angle', 'copy_count']),
        ];
    }
}
