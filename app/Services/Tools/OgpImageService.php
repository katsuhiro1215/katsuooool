<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use InvalidArgumentException;

class OgpImageService
{
    private const TARGET_WIDTH = 1200;

    private const TARGET_HEIGHT = 630;

    public function generate(string $inputPath, string $outputPath, string $mimeType): int
    {
        $image = $this->makeManager()->read($inputPath);
        $image->cover(self::TARGET_WIDTH, self::TARGET_HEIGHT);

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
