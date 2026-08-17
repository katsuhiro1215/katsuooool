<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use InvalidArgumentException;

class ImageResizeService
{
    public function resize(string $inputPath, string $outputPath, string $mimeType, ?int $width, ?int $height): int
    {
        if ($width === null && $height === null) {
            throw new InvalidArgumentException('Either width or height must be given.');
        }

        $image = $this->makeManager()->read($inputPath);
        $image->scale($width, $height);

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
