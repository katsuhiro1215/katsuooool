<?php

use App\Services\Tools\ImageRotateService;
use Illuminate\Http\UploadedFile;

function makeRotateTestInputFile(string $extension, int $width = 100, int $height = 60): string
{
    $upload = match ($extension) {
        'jpg' => UploadedFile::fake()->image('input.jpg', $width, $height),
        'png' => UploadedFile::fake()->image('input.png', $width, $height),
        'webp' => UploadedFile::fake()->image('input.webp', $width, $height),
    };

    $path = sys_get_temp_dir().'/'.uniqid('image-rotate-test-', true).'.'.$extension;
    file_put_contents($path, file_get_contents($upload->getRealPath()));

    return $path;
}

test('rotating left by 90 degrees swaps width and height', function () {
    $service = new ImageRotateService;
    $input = makeRotateTestInputFile('jpg', 100, 60);
    $output = sys_get_temp_dir().'/'.uniqid('image-rotate-out-', true).'.jpg';

    $service->rotate($input, $output, 'image/jpeg', 'left');

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(60);
    expect($dimensions[1])->toBe(100);

    @unlink($input);
    @unlink($output);
});

test('rotating right by 90 degrees swaps width and height', function () {
    $service = new ImageRotateService;
    $input = makeRotateTestInputFile('jpg', 100, 60);
    $output = sys_get_temp_dir().'/'.uniqid('image-rotate-out-', true).'.jpg';

    $service->rotate($input, $output, 'image/jpeg', 'right');

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(60);
    expect($dimensions[1])->toBe(100);

    @unlink($input);
    @unlink($output);
});

test('rotating 180 degrees keeps the same width and height', function () {
    $service = new ImageRotateService;
    $input = makeRotateTestInputFile('jpg', 100, 60);
    $output = sys_get_temp_dir().'/'.uniqid('image-rotate-out-', true).'.jpg';

    $service->rotate($input, $output, 'image/jpeg', '180');

    $dimensions = getimagesize($output);
    expect($dimensions[0])->toBe(100);
    expect($dimensions[1])->toBe(60);

    @unlink($input);
    @unlink($output);
});

test('rotating left moves a top-left marker to the bottom-left (counter-clockwise)', function () {
    $gdImage = imagecreatetruecolor(100, 50);
    $white = imagecolorallocate($gdImage, 255, 255, 255);
    imagefill($gdImage, 0, 0, $white);
    $red = imagecolorallocate($gdImage, 255, 0, 0);
    imagefilledrectangle($gdImage, 0, 0, 9, 9, $red);

    $input = sys_get_temp_dir().'/'.uniqid('image-rotate-marker-', true).'.png';
    imagepng($gdImage, $input);
    imagedestroy($gdImage);

    $output = sys_get_temp_dir().'/'.uniqid('image-rotate-marker-out-', true).'.png';

    $service = new ImageRotateService;
    $service->rotate($input, $output, 'image/png', 'left');

    $rotatedImage = imagecreatefrompng($output);
    $rgb = imagecolorat($rotatedImage, 2, 92);
    $colors = imagecolorsforindex($rotatedImage, $rgb);
    imagedestroy($rotatedImage);

    expect($colors['red'])->toBeGreaterThan(200);
    expect($colors['green'])->toBeLessThan(50);

    @unlink($input);
    @unlink($output);
});

test('rotating right moves a top-left marker to the top-right (clockwise)', function () {
    $gdImage = imagecreatetruecolor(100, 50);
    $white = imagecolorallocate($gdImage, 255, 255, 255);
    imagefill($gdImage, 0, 0, $white);
    $red = imagecolorallocate($gdImage, 255, 0, 0);
    imagefilledrectangle($gdImage, 0, 0, 9, 9, $red);

    $input = sys_get_temp_dir().'/'.uniqid('image-rotate-marker-', true).'.png';
    imagepng($gdImage, $input);
    imagedestroy($gdImage);

    $output = sys_get_temp_dir().'/'.uniqid('image-rotate-marker-out-', true).'.png';

    $service = new ImageRotateService;
    $service->rotate($input, $output, 'image/png', 'right');

    $rotatedImage = imagecreatefrompng($output);
    $rgb = imagecolorat($rotatedImage, 42, 2);
    $colors = imagecolorsforindex($rotatedImage, $rgb);
    imagedestroy($rotatedImage);

    expect($colors['red'])->toBeGreaterThan(200);
    expect($colors['green'])->toBeLessThan(50);

    @unlink($input);
    @unlink($output);
});

test('rotate keeps the original format (webp stays webp)', function () {
    $service = new ImageRotateService;
    $input = makeRotateTestInputFile('webp', 100, 60);
    $output = sys_get_temp_dir().'/'.uniqid('image-rotate-out-', true).'.webp';

    $service->rotate($input, $output, 'image/webp', '180');

    expect(getimagesize($output)['mime'])->toBe('image/webp');

    @unlink($input);
    @unlink($output);
});

test('rotate rejects an unsupported direction', function () {
    $service = new ImageRotateService;
    $input = makeRotateTestInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('image-rotate-out-', true).'.jpg';

    expect(fn () => $service->rotate($input, $output, 'image/jpeg', 'upside-down'))
        ->toThrow(InvalidArgumentException::class);

    @unlink($input);
});

test('rotate rejects an unsupported mime type', function () {
    $service = new ImageRotateService;
    $input = makeRotateTestInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('image-rotate-out-', true).'.gif';

    expect(fn () => $service->rotate($input, $output, 'image/gif', '180'))
        ->toThrow(InvalidArgumentException::class);

    @unlink($input);
});
