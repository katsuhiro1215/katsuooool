<?php

use App\Services\Tools\FaviconGenerateService;
use Illuminate\Http\UploadedFile;

function makeFaviconTestInputFile(int $width, int $height): string
{
    $upload = UploadedFile::fake()->image('input.jpg', $width, $height);
    $path = sys_get_temp_dir().'/'.uniqid('favicon-test-', true).'.jpg';
    file_put_contents($path, file_get_contents($upload->getRealPath()));

    return $path;
}

test('generate produces all six standard favicon sizes at the correct dimensions', function () {
    $service = new FaviconGenerateService;
    $input = makeFaviconTestInputFile(1000, 700);
    $outputDir = sys_get_temp_dir().'/'.uniqid('favicon-out-', true);
    mkdir($outputDir);

    $files = $service->generate($input, $outputDir);

    expect($files)->toHaveCount(6);

    $expectedSizes = [
        'favicon-16x16.png' => 16,
        'favicon-32x32.png' => 32,
        'favicon-48x48.png' => 48,
        'apple-touch-icon.png' => 180,
        'android-chrome-192x192.png' => 192,
        'android-chrome-512x512.png' => 512,
    ];

    foreach ($expectedSizes as $filename => $size) {
        $match = collect($files)->firstWhere('filename', $filename);
        expect($match)->not->toBeNull();

        $dimensions = getimagesize($match['path']);
        expect($dimensions[0])->toBe($size);
        expect($dimensions[1])->toBe($size);
        expect($dimensions['mime'])->toBe('image/png');
    }

    foreach ($files as $file) {
        @unlink($file['path']);
    }
    @rmdir($outputDir);
    @unlink($input);
});

test('a portrait source image is still cropped to square favicons', function () {
    $service = new FaviconGenerateService;
    $input = makeFaviconTestInputFile(300, 900);
    $outputDir = sys_get_temp_dir().'/'.uniqid('favicon-out-', true);
    mkdir($outputDir);

    $files = $service->generate($input, $outputDir);

    foreach ($files as $file) {
        $dimensions = getimagesize($file['path']);
        expect($dimensions[0])->toBe($dimensions[1]);
        @unlink($file['path']);
    }

    @rmdir($outputDir);
    @unlink($input);
});
