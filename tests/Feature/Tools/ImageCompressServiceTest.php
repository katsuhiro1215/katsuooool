<?php

use App\Services\Tools\ImageCompressService;
use Illuminate\Http\UploadedFile;

function makeTempInputFile(string $extension, int $width = 800, int $height = 600): string
{
    $upload = match ($extension) {
        'jpg' => UploadedFile::fake()->image('input.jpg', $width, $height),
        'png' => UploadedFile::fake()->image('input.png', $width, $height),
        'webp' => UploadedFile::fake()->image('input.webp', $width, $height),
    };

    $path = sys_get_temp_dir().'/'.uniqid('image-compress-test-', true).'.'.$extension;
    file_put_contents($path, file_get_contents($upload->getRealPath()));

    return $path;
}

test('compress produces a valid, non-empty jpeg output file', function () {
    $service = new ImageCompressService;
    $input = makeTempInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('image-compress-out-', true).'.jpg';

    $outputBytes = $service->compress($input, $output, 'image/jpeg');

    expect(file_exists($output))->toBeTrue();
    expect($outputBytes)->toBeGreaterThan(0);
    expect(filesize($output))->toBe($outputBytes);

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(800);
    expect($dimensions[1])->toBe(600);
    expect($dimensions['mime'])->toBe('image/jpeg');

    @unlink($input);
    @unlink($output);
});

test('compress produces a valid png output file', function () {
    $service = new ImageCompressService;
    $input = makeTempInputFile('png');
    $output = sys_get_temp_dir().'/'.uniqid('image-compress-out-', true).'.png';

    $outputBytes = $service->compress($input, $output, 'image/png');

    expect(file_exists($output))->toBeTrue();
    expect($outputBytes)->toBeGreaterThan(0);
    expect(getimagesize($output)['mime'])->toBe('image/png');

    @unlink($input);
    @unlink($output);
});

test('compress produces a valid webp output file', function () {
    $service = new ImageCompressService;
    $input = makeTempInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('image-compress-out-', true).'.webp';

    $outputBytes = $service->compress($input, $output, 'image/webp');

    expect(file_exists($output))->toBeTrue();
    expect($outputBytes)->toBeGreaterThan(0);
    expect(getimagesize($output)['mime'])->toBe('image/webp');

    @unlink($input);
    @unlink($output);
});

test('compress rejects an unsupported mime type', function () {
    $service = new ImageCompressService;
    $input = makeTempInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('image-compress-out-', true).'.gif';

    expect(fn () => $service->compress($input, $output, 'image/gif'))
        ->toThrow(InvalidArgumentException::class);

    @unlink($input);
});
