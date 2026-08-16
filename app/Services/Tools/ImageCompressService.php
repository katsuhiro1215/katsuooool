<?php

namespace App\Services\Tools;

use InvalidArgumentException;
use Intervention\Image\ImageManager;

class ImageCompressService
{
    private const JPEG_QUALITY = 80;

    private const WEBP_QUALITY = 80;

    public function compress(string $inputPath, string $outputPath, string $mimeType): int
    {
        $image = $this->makeManager()->read($inputPath);

        $encoded = match ($mimeType) {
            'image/jpeg' => $image->toJpeg(quality: self::JPEG_QUALITY),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: self::WEBP_QUALITY),
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
