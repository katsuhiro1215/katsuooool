<?php

namespace App\Support;

class ImageDimensions
{
    /**
     * @return array{0: int, 1: int}|null
     */
    public static function read(string $path): ?array
    {
        $size = @getimagesize($path);

        if ($size !== false) {
            return [$size[0], $size[1]];
        }

        // HEIC など getimagesize が読めない形式は、Imagick のヘッダー読み取りで調べる（デコードしない）
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $imagick = new \Imagick;
            $imagick->pingImage($path);
            $dimensions = [$imagick->getImageWidth(), $imagick->getImageHeight()];
            $imagick->clear();

            return $dimensions;
        } catch (\ImagickException) {
            return null;
        }
    }
}
