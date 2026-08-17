<?php

namespace App\Services\Tools;

use Intervention\Image\ImageManager;
use RuntimeException;

class ImageColorExtractService
{
    private const COLOR_COUNT = 6;

    private const SAMPLE_DIMENSION = 100;

    private const QUANTIZE_STEP = 32;

    private const MIN_OPAQUE_ALPHA = 16;

    /**
     * @return list<array{hex: string, rgb: array{0: int, 1: int, 2: int}}>
     */
    public function extract(string $inputPath): array
    {
        $image = $this->makeManager()->read($inputPath);
        $image->scale(width: self::SAMPLE_DIMENSION, height: self::SAMPLE_DIMENSION);

        $buckets = [];

        for ($y = 0; $y < $image->height(); $y++) {
            for ($x = 0; $x < $image->width(); $x++) {
                [$r, $g, $b, $a] = $image->pickColor($x, $y)->toArray();

                if ($a < self::MIN_OPAQUE_ALPHA) {
                    continue;
                }

                $key = intdiv($r, self::QUANTIZE_STEP).'-'.intdiv($g, self::QUANTIZE_STEP).'-'.intdiv($b, self::QUANTIZE_STEP);

                $buckets[$key] ??= ['count' => 0, 'r' => 0, 'g' => 0, 'b' => 0];
                $buckets[$key]['count']++;
                $buckets[$key]['r'] += $r;
                $buckets[$key]['g'] += $g;
                $buckets[$key]['b'] += $b;
            }
        }

        if ($buckets === []) {
            throw new RuntimeException('No opaque pixels found to extract colors from.');
        }

        uasort($buckets, fn (array $left, array $right) => $right['count'] <=> $left['count']);

        return array_values(array_map(
            function (array $bucket): array {
                $r = (int) round($bucket['r'] / $bucket['count']);
                $g = (int) round($bucket['g'] / $bucket['count']);
                $b = (int) round($bucket['b'] / $bucket['count']);

                return [
                    'hex' => sprintf('#%02x%02x%02x', $r, $g, $b),
                    'rgb' => [$r, $g, $b],
                ];
            },
            array_slice($buckets, 0, self::COLOR_COUNT),
        ));
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
