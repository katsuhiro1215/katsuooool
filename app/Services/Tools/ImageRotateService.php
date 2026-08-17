<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use InvalidArgumentException;

class ImageRotateService
{
    /**
     * Intervention's rotate() turns counter-clockwise for a positive angle,
     * so "right" (clockwise) maps to a negative angle.
     */
    private const ANGLES = [
        'left' => 90,
        'right' => -90,
        '180' => 180,
    ];

    public function rotate(string $inputPath, string $outputPath, string $mimeType, string $direction): int
    {
        $angle = self::ANGLES[$direction] ?? throw new InvalidArgumentException("Unsupported rotation direction: {$direction}");

        $image = $this->makeManager()->read($inputPath);
        $image->rotate($angle);

        $encoded = match ($mimeType) {
            'image/jpeg' => $image->toJpeg(),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(),
            default => throw new InvalidArgumentException("Unsupported mime type: {$mimeType}"),
        };

        $encoded->save($outputPath);

        return $encoded->size();
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
