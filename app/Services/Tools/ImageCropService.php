<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use InvalidArgumentException;

class ImageCropService
{
    public function crop(string $inputPath, string $outputPath, string $mimeType, int $x, int $y, int $width, int $height): int
    {
        $image = $this->makeManager()->read($inputPath);
        $image->crop($width, $height, $x, $y);

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
