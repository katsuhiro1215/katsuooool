<?php

namespace App\Support;

class ImageMetadata
{
    /**
     * JPEG に GPS（緯度）の情報が入っているか。読めない場合は false。
     */
    public static function hasGps(string $path): bool
    {
        if (! function_exists('exif_read_data') || ! is_file($path)) {
            return false;
        }

        try {
            $exif = @exif_read_data($path, 'GPS', true);
        } catch (\Throwable) {
            return false;
        }

        return is_array($exif) && ! empty($exif['GPS']['GPSLatitude']);
    }
}
