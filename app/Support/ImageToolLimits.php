<?php

namespace App\Support;

class ImageToolLimits
{
    public static function maxFiles(bool $isPremium): int
    {
        return (int) config('image_tools.max_files.'.($isPremium ? 'premium' : 'free'));
    }

    public static function avifMaxFiles(bool $isPremium): int
    {
        return (int) config('image_tools.avif_max_files.'.($isPremium ? 'premium' : 'free'));
    }

    public static function maxFileBytes(): int
    {
        return (int) config('image_tools.max_file_bytes');
    }

    public static function maxPixels(): int
    {
        return (int) config('image_tools.max_pixels');
    }

    public static function avifMaxPixels(): int
    {
        return (int) config('image_tools.avif_max_pixels');
    }

    public static function pngIndexedMaxPixels(): int
    {
        return (int) config('image_tools.png_indexed_max_pixels');
    }
}
