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

test('targetSize never enlarges and reports clamping', function () {
    $service = new ImageResizeService;

    expect($service->targetSize(1000, 500, 400, null))->toBe(['width' => 400, 'height' => 200, 'clamped' => false])
        ->and($service->targetSize(1000, 500, 4000, null))->toBe(['width' => 1000, 'height' => 500, 'clamped' => true])
        ->and($service->targetSize(1000, 500, null, 2000))->toBe(['width' => 1000, 'height' => 500, 'clamped' => true])
        ->and($service->targetSize(1000, 500, 400, 100))->toBe(['width' => 200, 'height' => 100, 'clamped' => false])
        ->and($service->targetSize(1000, 500, null, null, 50))->toBe(['width' => 500, 'height' => 250, 'clamped' => false])
        ->and($service->targetSize(1000, 500, null, null, 100))->toBe(['width' => 1000, 'height' => 500, 'clamped' => false]);
});

test('resize by percent produces the scaled dimensions', function () {
    $input = makeResizeTestInputFile('jpg', 800, 600);
    $output = sys_get_temp_dir().'/'.uniqid('resize-percent-', true).'.jpg';

    (new ImageResizeService)->resize($input, $output, 'image/jpeg', null, null, 25);

    expect(getimagesize($output)[0])->toBe(200)
        ->and(getimagesize($output)[1])->toBe(150);

    @unlink($input);
    @unlink($output);
});

test('resize with a larger target keeps the original size', function () {
    $input = makeResizeTestInputFile('jpg', 300, 200);
    $output = sys_get_temp_dir().'/'.uniqid('resize-noenlarge-', true).'.jpg';

    (new ImageResizeService)->resize($input, $output, 'image/jpeg', 3000, null);

    expect([getimagesize($output)[0], getimagesize($output)[1]])->toBe([300, 200]);

    @unlink($input);
    @unlink($output);
});
