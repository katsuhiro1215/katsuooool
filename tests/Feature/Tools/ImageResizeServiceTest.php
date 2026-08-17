<?php

use App\Services\Tools\ImageResizeService;
use Illuminate\Http\UploadedFile;

function makeResizeTestInputFile(string $extension, int $width = 800, int $height = 600): string
{
    $upload = match ($extension) {
        'jpg' => UploadedFile::fake()->image('input.jpg', $width, $height),
        'png' => UploadedFile::fake()->image('input.png', $width, $height),
        'webp' => UploadedFile::fake()->image('input.webp', $width, $height),
    };

    $path = sys_get_temp_dir().'/'.uniqid('image-resize-test-', true).'.'.$extension;
    file_put_contents($path, file_get_contents($upload->getRealPath()));

    return $path;
}

test('resize with both width and height fits the image within those bounds, preserving aspect ratio', function () {
    $service = new ImageResizeService;
    $input = makeResizeTestInputFile('jpg', 800, 600);
    $output = sys_get_temp_dir().'/'.uniqid('image-resize-out-', true).'.jpg';

    $service->resize($input, $output, 'image/jpeg', 400, 400);

    expect(file_exists($output))->toBeTrue();
    $dimensions = getimagesize($output);
    // 800x600 (4:3) fit within 400x400 -> 400x300
    expect($dimensions[0])->toBe(400);
    expect($dimensions[1])->toBe(300);

    @unlink($input);
    @unlink($output);
});

test('resize with only width preserves aspect ratio', function () {
    $service = new ImageResizeService;
    $input = makeResizeTestInputFile('jpg', 800, 600);
    $output = sys_get_temp_dir().'/'.uniqid('image-resize-out-', true).'.jpg';

    $service->resize($input, $output, 'image/jpeg', 400, null);

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(400);
    expect($dimensions[1])->toBe(300);

    @unlink($input);
    @unlink($output);
});

test('resize with only height preserves aspect ratio', function () {
    $service = new ImageResizeService;
    $input = makeResizeTestInputFile('jpg', 800, 600);
    $output = sys_get_temp_dir().'/'.uniqid('image-resize-out-', true).'.jpg';

    $service->resize($input, $output, 'image/jpeg', null, 300);

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(400);
    expect($dimensions[1])->toBe(300);

    @unlink($input);
    @unlink($output);
});

test('resize keeps the original format (png stays png)', function () {
    $service = new ImageResizeService;
    $input = makeResizeTestInputFile('png', 800, 600);
    $output = sys_get_temp_dir().'/'.uniqid('image-resize-out-', true).'.png';

    $service->resize($input, $output, 'image/png', 400, null);

    expect(getimagesize($output)['mime'])->toBe('image/png');

    @unlink($input);
    @unlink($output);
});

test('resize throws when neither width nor height is given', function () {
    $service = new ImageResizeService;
    $input = makeResizeTestInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('image-resize-out-', true).'.jpg';

    expect(fn () => $service->resize($input, $output, 'image/jpeg', null, null))
        ->toThrow(InvalidArgumentException::class);

    @unlink($input);
});

test('resize rejects an unsupported mime type', function () {
    $service = new ImageResizeService;
    $input = makeResizeTestInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('image-resize-out-', true).'.gif';

    expect(fn () => $service->resize($input, $output, 'image/gif', 100, 100))
        ->toThrow(InvalidArgumentException::class);

    @unlink($input);
});
