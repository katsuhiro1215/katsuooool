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
        $source = $this->makeManager()->read($inputPath);
        $files = [];

        foreach (self::SIZES as $filename => $size) {
            $resized = (clone $source)->cover($size, $size);
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
