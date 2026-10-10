<?php

namespace App\Support;

use Imagick;

class ImagickLimits
{
    private const TYPES = [
        'memory' => 'RESOURCETYPE_MEMORY',
        'map' => 'RESOURCETYPE_MAP',
        'disk' => 'RESOURCETYPE_DISK',
        'thread' => 'RESOURCETYPE_THREAD',
    ];

    /**
     * 大きな画像で app コンテナがメモリ不足になるのを防ぐ。
     * 上限を超えた分は、メモリではなくディスクのキャッシュに逃がされる。
     */
    public static function apply(): void
    {
        if (! class_exists(Imagick::class)) {
            return;
        }

        foreach (self::TYPES as $key => $constant) {
            $value = config("image_tools.imagick.{$key}");

            if ($value !== null) {
                Imagick::setResourceLimit(constant(Imagick::class.'::'.$constant), (int) $value);
            }
        }
    }
}
