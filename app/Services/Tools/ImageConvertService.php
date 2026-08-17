<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use InvalidArgumentException;

class ImageConvertService
{
    private const QUALITY = 90;

    public function convert(string $inputPath, string $outputPath, string $targetMimeType): int
    {
        $image = $this->makeManager()->read($inputPath);

        $encoded = match ($targetMimeType) {
            'image/jpeg' => $image->toJpeg(quality: self::QUALITY),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: self::QUALITY),
            'image/avif' => $image->toAvif(quality: self::QUALITY),
            default => throw new InvalidArgumentException("Unsupported target mime type: {$targetMimeType}"),
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
