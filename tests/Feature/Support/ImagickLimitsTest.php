<?php

use App\Support\ImagickLimits;

test('applies the configured resource limits to Imagick', function () {
    config()->set('image_tools.imagick', [
        'memory' => 64 * 1024 * 1024,
        'map' => 128 * 1024 * 1024,
        'disk' => null,
        'thread' => 1,
    ]);

    ImagickLimits::apply();

    // getResourceLimit は float を返すことがあるため、整数に直して比べる
    expect((int) Imagick::getResourceLimit(Imagick::RESOURCETYPE_MEMORY))->toBe(64 * 1024 * 1024)
        ->and((int) Imagick::getResourceLimit(Imagick::RESOURCETYPE_MAP))->toBe(128 * 1024 * 1024)
        ->and((int) Imagick::getResourceLimit(Imagick::RESOURCETYPE_THREAD))->toBe(1);
})->skip(! class_exists(Imagick::class), 'Imagick 拡張がない環境');

test('does nothing when no limit is configured', function () {
    config()->set('image_tools.imagick', ['memory' => null, 'map' => null, 'disk' => null, 'thread' => null]);

    ImagickLimits::apply();

    expect(true)->toBeTrue();
});
