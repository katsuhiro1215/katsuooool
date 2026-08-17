<?php

use App\Services\Tools\ImageColorExtractService;
use Illuminate\Http\UploadedFile;

function makeColorExtractTestInputFile(string $extension, int $width = 100, int $height = 100): string
{
    $path = sys_get_temp_dir().'/'.uniqid('image-color-extract-test-', true).'.'.$extension;

    $upload = match ($extension) {
        'jpg' => UploadedFile::fake()->image('input.jpg', $width, $height),
        'png' => UploadedFile::fake()->image('input.png', $width, $height),
    };

    file_put_contents($path, file_get_contents($upload->getRealPath()));

    return $path;
}

test('extract returns a single dominant color for a solid-color image', function () {
    $gdImage = imagecreatetruecolor(100, 100);
    $color = imagecolorallocate($gdImage, 51, 102, 255);
    imagefill($gdImage, 0, 0, $color);
    $path = sys_get_temp_dir().'/'.uniqid('image-color-extract-solid-', true).'.png';
    imagepng($gdImage, $path);
    imagedestroy($gdImage);

    $service = new ImageColorExtractService;
    $colors = $service->extract($path);

    expect($colors)->toHaveCount(1);
    expect($colors[0]['hex'])->toBe('#3366ff');
    expect($colors[0]['rgb'])->toBe([51, 102, 255]);

    @unlink($path);
});

test('extract returns at most 6 colors for a multi-color image', function () {
    $gdImage = imagecreatetruecolor(100, 100);
    $palette = [
        [255, 0, 0], [0, 255, 0], [0, 0, 255],
        [255, 255, 0], [0, 255, 255], [255, 0, 255],
        [128, 128, 128], [255, 255, 255],
    ];

    foreach ($palette as $i => [$r, $g, $b]) {
        $color = imagecolorallocate($gdImage, $r, $g, $b);
        imagefilledrectangle($gdImage, $i * 12, 0, ($i + 1) * 12, 100, $color);
    }

    $path = sys_get_temp_dir().'/'.uniqid('image-color-extract-multi-', true).'.png';
    imagepng($gdImage, $path);
    imagedestroy($gdImage);

    $service = new ImageColorExtractService;
    $colors = $service->extract($path);

    expect(count($colors))->toBeLessThanOrEqual(6);
    expect(count($colors))->toBeGreaterThan(1);

    foreach ($colors as $color) {
        expect($color)->toHaveKeys(['hex', 'rgb']);
        expect($color['hex'])->toMatch('/^#[0-9a-f]{6}$/');
        expect($color['rgb'])->toHaveCount(3);
    }

    @unlink($path);
});

test('extract ignores fully transparent pixels', function () {
    $gdImage = imagecreatetruecolor(100, 100);
    imagealphablending($gdImage, false);
    imagesavealpha($gdImage, true);

    $transparent = imagecolorallocatealpha($gdImage, 0, 0, 0, 127);
    imagefill($gdImage, 0, 0, $transparent);

    $opaque = imagecolorallocatealpha($gdImage, 200, 50, 50, 0);
    imagefilledrectangle($gdImage, 40, 40, 60, 60, $opaque);

    $path = sys_get_temp_dir().'/'.uniqid('image-color-extract-alpha-', true).'.png';
    imagepng($gdImage, $path);
    imagedestroy($gdImage);

    $service = new ImageColorExtractService;
    $colors = $service->extract($path);

    expect($colors)->toHaveCount(1);
    expect($colors[0]['rgb'])->toBe([200, 50, 50]);

    @unlink($path);
});

test('extract throws when every pixel is fully transparent', function () {
    $gdImage = imagecreatetruecolor(20, 20);
    imagealphablending($gdImage, false);
    imagesavealpha($gdImage, true);
    $transparent = imagecolorallocatealpha($gdImage, 0, 0, 0, 127);
    imagefill($gdImage, 0, 0, $transparent);

    $path = sys_get_temp_dir().'/'.uniqid('image-color-extract-fully-transparent-', true).'.png';
    imagepng($gdImage, $path);
    imagedestroy($gdImage);

    $service = new ImageColorExtractService;

    expect(fn () => $service->extract($path))->toThrow(RuntimeException::class);

    @unlink($path);
});

test('extract works on a real jpeg photo fixture', function () {
    $path = makeColorExtractTestInputFile('jpg', 400, 300);

    $service = new ImageColorExtractService;
    $colors = $service->extract($path);

    expect(count($colors))->toBeGreaterThanOrEqual(1);
    expect(count($colors))->toBeLessThanOrEqual(6);

    @unlink($path);
});
