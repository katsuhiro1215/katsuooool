<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

class ImageResizeService
{
    private const MAX_OUTPUT_PIXELS = 25_000_000;

    public function resize(string $inputPath, string $outputPath, string $mimeType, ?int $width, ?int $height): int
    {
        if ($width === null && $height === null) {
            throw new InvalidArgumentException('Either width or height must be given.');
        }

        $image = $this->makeManager()->read($inputPath);

        [$projectedWidth, $projectedHeight] = $this->projectDimensions($image->width(), $image->height(), $width, $height);

        if ($projectedWidth * $projectedHeight > self::MAX_OUTPUT_PIXELS) {
            throw new RuntimeException('Resized image would exceed the maximum output resolution.');
        }

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

    /**
     * @return array{0: int, 1: int}
     */
    private function projectDimensions(int $originalWidth, int $originalHeight, ?int $width, ?int $height): array
    {
        if ($width !== null && $height !== null) {
            $ratio = min($width / $originalWidth, $height / $originalHeight);
        } elseif ($width !== null) {
            $ratio = $width / $originalWidth;
        } else {
            $ratio = $height / $originalHeight;
        }

        return [
            (int) round($originalWidth * $ratio),
            (int) round($originalHeight * $ratio),
        ];
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
