<?php

use App\Support\ImageMetadata;

test('detects a jpeg with GPS coordinates', function () {
    expect(ImageMetadata::hasGps(base_path('tests/fixtures/gps.jpg')))->toBeTrue();
})->skip(! function_exists('exif_read_data'), 'exif 拡張が無い環境');

test('a jpeg without GPS is false', function () {
    $path = sys_get_temp_dir().'/'.uniqid('nogps-', true).'.jpg';
    $gd = imagecreatetruecolor(50, 50);
    imagejpeg($gd, $path);
    imagedestroy($gd);

    expect(ImageMetadata::hasGps($path))->toBeFalse();

    @unlink($path);
});

test('a missing or non-image file is false', function () {
    expect(ImageMetadata::hasGps('/definitely/not/here.jpg'))->toBeFalse();
});
