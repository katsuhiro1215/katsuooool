<?php

use App\Support\ImageDimensions;

test('reads the width and height of a jpeg', function () {
    $path = sys_get_temp_dir().'/'.uniqid('dims-', true).'.jpg';
    $gd = imagecreatetruecolor(120, 80);
    imagejpeg($gd, $path);
    imagedestroy($gd);

    expect(ImageDimensions::read($path))->toBe([120, 80]);

    @unlink($path);
});

test('returns null for a file that is not an image', function () {
    $path = sys_get_temp_dir().'/'.uniqid('dims-', true).'.txt';
    file_put_contents($path, 'not an image');

    expect(ImageDimensions::read($path))->toBeNull();

    @unlink($path);
});

test('reads the size of a HEIC photo through Imagick', function () {
    expect(ImageDimensions::read(base_path('tests/fixtures/sample.heic')))->toBe([400, 300]);
})->skip(! class_exists(Imagick::class) || ! in_array('HEIC', Imagick::queryFormats(), true), 'Imagick が HEIC に対応していない環境');
