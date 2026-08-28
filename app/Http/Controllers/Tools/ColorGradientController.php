<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorExampleCopyable;
use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\ColorExample;
use Illuminate\Http\Request;

class ColorGradientController extends TrackedToolController
{
    use ColorExampleCopyable;

    protected function slug(): string
    {
        return 'color-gradient';
    }

    protected function page(): string
    {
        return 'ColorGradient';
    }

    protected function exampleType(): string
    {
        return 'gradient';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return [
            'examples' => ColorExample::ofType('gradient')->get(['id', 'title', 'colors', 'angle', 'copy_count']),
        ];
    }
}
