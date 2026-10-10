<?php

use App\Support\ImageToolLimits;

test('default limits match the product spec', function () {
    expect(ImageToolLimits::maxFiles(false))->toBe(10)
        ->and(ImageToolLimits::maxFiles(true))->toBe(20)
        ->and(ImageToolLimits::avifMaxFiles(false))->toBe(2)
        ->and(ImageToolLimits::avifMaxFiles(true))->toBe(5)
        ->and(ImageToolLimits::maxFileBytes())->toBe(10 * 1024 * 1024)
        ->and(ImageToolLimits::maxPixels())->toBe(25_000_000);
});

test('limits follow the configuration', function () {
    config()->set('image_tools.max_files.free', 4);
    config()->set('image_tools.avif_max_files.premium', 9);

    expect(ImageToolLimits::maxFiles(false))->toBe(4)
        ->and(ImageToolLimits::avifMaxFiles(true))->toBe(9);
});
