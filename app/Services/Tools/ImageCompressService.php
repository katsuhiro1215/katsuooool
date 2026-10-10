<?php

namespace App\Services\Tools;

use App\Support\ImageToolLimits;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;

class ImageCompressService
{
    private const JPEG_QUALITY = 80;

    private const WEBP_QUALITY = 80;

    public function compress(string $inputPath, string $outputPath, string $mimeType): int
    {
        $image = $this->makeManager()->read($inputPath);

        $encoded = match ($mimeType) {
            'image/jpeg' => $image->toJpeg(quality: self::JPEG_QUALITY),
            'image/png' => $this->encodeSmallestPng($image),
            'image/webp' => $image->toWebp(quality: self::WEBP_QUALITY),
            default => throw new InvalidArgumentException("Unsupported mime type: {$mimeType}"),
        };

        $encoded->save($outputPath);

        return $encoded->size();
    }

    /**
     * A truecolor PNG re-encode frequently doesn't shrink already-optimized
     * PNGs (or even grows them slightly), while palette (indexed) encoding
     * shrinks most UI/screenshot-style PNGs significantly at the cost of
     * capping the image to 255 colors. Encoding both and keeping whichever
     * is smaller gets the size benefit without a fixed quality trade-off.
     */
    private function encodeSmallestPng(ImageInterface $image): EncodedImageInterface
    {
        $truecolor = $image->toPng();

        // 256色化は画素数に比例して遅くなる（2,500万画素で約265秒）ため、大きい画像では省く
        if ($image->width() * $image->height() > ImageToolLimits::pngIndexedMaxPixels()) {
            return $truecolor;
        }

        $indexed = $image->toPng(indexed: true);

        return $indexed->size() <= $truecolor->size() ? $indexed : $truecolor;
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
