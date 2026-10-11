<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;
use RuntimeException;

class ImageConvertService
{
    private const QUALITY = 90;

    // AVIF encoding is far more compute-intensive than JPEG/WebP; a lower
    // quality keeps encode time and memory bounded on shared hosting, where
    // some GD/libavif builds have been observed to silently emit an empty
    // file at high quality settings under resource pressure.
    private const AVIF_QUALITY = 80;

    public function convert(string $inputPath, string $outputPath, string $targetMimeType): int
    {
        $image = $this->makeManager()->read($inputPath);

        $encoded = match ($targetMimeType) {
            'image/jpeg' => $image->toJpeg(quality: self::QUALITY),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: self::QUALITY),
            'image/avif' => $this->encodeAvif($image),
            default => throw new InvalidArgumentException("Unsupported target mime type: {$targetMimeType}"),
        };

        $outputBytes = $encoded->size();

        if ($outputBytes === 0) {
            throw new RuntimeException("Encoding to {$targetMimeType} produced an empty file.");
        }

        $encoded->save($outputPath);

        return $outputBytes;
    }

    /**
     * AVIF のエンコード速度を上げる。8 で約3倍速い（12MP: 9.3秒→3.3秒）。メモリは変わらない。
     * Imagick 以外（GD）には速度を渡す手段がないので、何もしない。
     */
    public function applyAvifSpeed(ImageInterface $image): void
    {
        $native = $image->core()->native();

        if ($native instanceof \Imagick) {
            $native->setOption('heic:speed', (string) config('image_tools.avif_speed', 8));
        }
    }

    private function encodeAvif(ImageInterface $image): EncodedImageInterface
    {
        $this->applyAvifSpeed($image);

        return $image->toAvif(quality: self::AVIF_QUALITY);
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
