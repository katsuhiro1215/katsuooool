<?php

use App\Services\Tools\OgpImageService;
use Illuminate\Http\UploadedFile;

function makeOgpTestInputFile(string $extension, int $width, int $height): string
{
    $upload = match ($extension) {
        'jpg' => UploadedFile::fake()->image('input.jpg', $width, $height),
        'png' => UploadedFile::fake()->image('input.png', $width, $height),
        'webp' => UploadedFile::fake()->image('input.webp', $width, $height),
    };

    $path = sys_get_temp_dir().'/'.uniqid('ogp-image-test-', true).'.'.$extension;
    file_put_contents($path, file_get_contents($upload->getRealPath()));

    return $path;
}

test('a landscape image is cropped and resized to exactly 1200x630', function () {
    $service = new OgpImageService;
    $input = makeOgpTestInputFile('jpg', 2000, 800);
    $output = sys_get_temp_dir().'/'.uniqid('ogp-image-out-', true).'.jpg';

    $service->generate($input, $output, 'image/jpeg');

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(1200);
    expect($dimensions[1])->toBe(630);

    @unlink($input);
    @unlink($output);
});

test('a portrait image is cropped and resized to exactly 1200x630', function () {
    $service = new OgpImageService;
    $input = makeOgpTestInputFile('jpg', 600, 1400);
    $output = sys_get_temp_dir().'/'.uniqid('ogp-image-out-', true).'.jpg';

    $service->generate($input, $output, 'image/jpeg');

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(1200);
    expect($dimensions[1])->toBe(630);

    @unlink($input);
    @unlink($output);
});

test('a square image is cropped and resized to exactly 1200x630', function () {
    $service = new OgpImageService;
    $input = makeOgpTestInputFile('png', 500, 500);
    $output = sys_get_temp_dir().'/'.uniqid('ogp-image-out-', true).'.png';

    $service->generate($input, $output, 'image/png');

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(1200);
    expect($dimensions[1])->toBe(630);

    @unlink($input);
    @unlink($output);
});

test('a smaller-than-target image is still upscaled to exactly 1200x630', function () {
    $service = new OgpImageService;
    $input = makeOgpTestInputFile('jpg', 300, 200);
    $output = sys_get_temp_dir().'/'.uniqid('ogp-image-out-', true).'.jpg';

    $service->generate($input, $output, 'image/jpeg');

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(1200);
    expect($dimensions[1])->toBe(630);

    @unlink($input);
    @unlink($output);
});

test('generate keeps the original format (webp stays webp)', function () {
    $service = new OgpImageService;
    $input = makeOgpTestInputFile('webp', 2000, 800);
    $output = sys_get_temp_dir().'/'.uniqid('ogp-image-out-', true).'.webp';

    $service->generate($input, $output, 'image/webp');

    expect(getimagesize($output)['mime'])->toBe('image/webp');

    @unlink($input);
    @unlink($output);
});

test('generate rejects an unsupported mime type', function () {
    $service = new OgpImageService;
    $input = makeOgpTestInputFile('jpg', 2000, 800);
    $output = sys_get_temp_dir().'/'.uniqid('ogp-image-out-', true).'.gif';

    expect(fn () => $service->generate($input, $output, 'image/gif'))
        ->toThrow(InvalidArgumentException::class);

    @unlink($input);
});
