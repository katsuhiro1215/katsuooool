<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

class ImageResizeService
{
    private const MAX_OUTPUT_PIXELS = 25_000_000;

    public function resize(string $inputPath, string $outputPath, string $mimeType, ?int $width, ?int $height, ?int $percent = null): int
    {
        if ($width === null && $height === null && $percent === null) {
            throw new InvalidArgumentException('Either width, height or percent must be given.');
        }

        $image = $this->makeManager()->read($inputPath);

        $target = $this->targetSize($image->width(), $image->height(), $width, $height, $percent);

        if ($target['width'] * $target['height'] > self::MAX_OUTPUT_PIXELS) {
            throw new RuntimeException('Resized image would exceed the maximum output resolution.');
        }

        $image->resize($target['width'], $target['height']);

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
     * 縦横比を保ち、拡大はしない（縮小率は最大 1）。元より大きい指定は元のサイズに収め、clamped を立てる。
     *
     * @return array{width: int, height: int, clamped: bool}
     */
    public function targetSize(int $originalWidth, int $originalHeight, ?int $width, ?int $height, ?int $percent = null): array
    {
        if ($percent !== null) {
            $ratio = $percent / 100;
        } elseif ($width !== null && $height !== null) {
            $ratio = min($width / $originalWidth, $height / $originalHeight);
        } elseif ($width !== null) {
            $ratio = $width / $originalWidth;
        } else {
            $ratio = ($height ?? $originalHeight) / $originalHeight;
        }

        $clamped = $ratio > 1;
        $ratio = min($ratio, 1.0);

        return [
            'width' => max(1, (int) round($originalWidth * $ratio)),
            'height' => max(1, (int) round($originalHeight * $ratio)),
            'clamped' => $clamped,
        ];
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
