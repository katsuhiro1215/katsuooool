<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;

class FaviconGenerateService
{
    private const SIZES = [
        'favicon-16x16.png' => 16,
        'favicon-32x32.png' => 32,
        'favicon-48x48.png' => 48,
        'apple-touch-icon.png' => 180,
        'android-chrome-192x192.png' => 192,
        'android-chrome-512x512.png' => 512,
    ];

    /**
     * @return list<array{filename: string, path: string}>
     */
    public function generate(string $inputPath, string $outputDir): array
    {
        // Crop to a square once at the largest target size, then downscale
        // that (already-square) base for the remaining sizes. Cloning the
        // full-resolution source for every size would hold up to six
        // full-size copies in memory at once on shared hosting; resizing a
        // square to a smaller square needs no further cropping, so this is
        // visually identical to covering directly from the original for
        // each size.
        $maxSize = max(self::SIZES);
        $base = $this->makeManager()->read($inputPath)->cover($maxSize, $maxSize);
        $files = [];

        foreach (self::SIZES as $filename => $size) {
            $resized = $size === $maxSize ? $base : (clone $base)->cover($size, $size);
            $outputPath = rtrim($outputDir, '/').'/'.$filename;
            $resized->toPng()->save($outputPath);

            $files[] = ['filename' => $filename, 'path' => $outputPath];
        }

        return $files;
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
