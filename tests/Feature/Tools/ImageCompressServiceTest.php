<?php

use App\Services\Tools\CliRunner;
use App\Services\Tools\ImageCompressService;
use Illuminate\Http\UploadedFile;
use Tests\Support\FakeCli;

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

test('compress uses palette encoding for a low-color png when it produces a smaller file', function () {
    // A flat-color, screenshot-style PNG: GD's truecolor PNG re-encode
    // typically doesn't shrink it (or even grows it slightly), while
    // palette (indexed) encoding shrinks it significantly. This is the
    // exact scenario reported as "compress does nothing" for PNGs.
    $width = 1200;
    $height = 800;
    $gdImage = imagecreatetruecolor($width, $height);
    imagefilledrectangle($gdImage, 0, 0, $width, $height, imagecolorallocate($gdImage, 245, 245, 245));
    imagefilledrectangle($gdImage, 0, 0, $width, 80, imagecolorallocate($gdImage, 30, 64, 175));
    for ($i = 0; $i < 6; $i++) {
        imagefilledrectangle($gdImage, 20 + $i * 180, 120, 20 + $i * 180 + 150, 300, imagecolorallocate($gdImage, 220, 220, 230));
    }

    $inputPath = sys_get_temp_dir().'/'.uniqid('image-compress-screenshot-', true).'.png';
    imagepng($gdImage, $inputPath);
    imagedestroy($gdImage);

    $originalSize = filesize($inputPath);

    $service = new ImageCompressService(new FakeCli);
    $output = sys_get_temp_dir().'/'.uniqid('image-compress-screenshot-out-', true).'.png';

    $outputBytes = $service->compress($inputPath, $output, 'image/png');

    expect($outputBytes)->toBeLessThan($originalSize);

    @unlink($inputPath);
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

test('compress skips palette encoding for a png above the indexed pixel ceiling', function () {
    // 256色化は画素数に比例して遅くなる（2,500万画素で約265秒）ため、上限を超える画像では省く。
    // 上限を 1 画素にして、パレット化で縮むはずの画像が縮まないこと（= 省かれたこと）を確かめる。
    $gdImage = imagecreatetruecolor(1200, 800);
    imagefilledrectangle($gdImage, 0, 0, 1200, 800, imagecolorallocate($gdImage, 245, 245, 245));
    imagefilledrectangle($gdImage, 0, 0, 1200, 80, imagecolorallocate($gdImage, 30, 64, 175));
    $inputPath = sys_get_temp_dir().'/'.uniqid('image-compress-indexed-ceiling-', true).'.png';
    imagepng($gdImage, $inputPath);
    imagedestroy($gdImage);

    $service = new ImageCompressService(new FakeCli);
    $withPalette = sys_get_temp_dir().'/'.uniqid('out-a-', true).'.png';
    $withoutPalette = sys_get_temp_dir().'/'.uniqid('out-b-', true).'.png';

    config()->set('image_tools.png_indexed_max_pixels', 2_500_000);
    $paletteBytes = $service->compress($inputPath, $withPalette, 'image/png');

    config()->set('image_tools.png_indexed_max_pixels', 1);
    $truecolorBytes = $service->compress($inputPath, $withoutPalette, 'image/png');

    expect($truecolorBytes)->toBeGreaterThan($paletteBytes);

    @unlink($inputPath);
    @unlink($withPalette);
    @unlink($withoutPalette);
});

function compressWithFakeCli(FakeCli $cli, string $extension, string $mime, string $level = 'recommended'): array
{
    $input = makeTempInputFile($extension);
    $output = sys_get_temp_dir().'/'.uniqid('image-compress-cli-', true).'.'.$extension;
    $bytes = (new ImageCompressService($cli))->compress($input, $output, $mime, $level);

    return [$bytes, $input, $output];
}

test('jpeg uses jpegoptim with the recommended max and never strips EXIF or ICC', function () {
    $cli = new FakeCli(['jpegoptim']);

    [, $input, $output] = compressWithFakeCli($cli, 'jpg', 'image/jpeg');

    expect($cli->commands)->toHaveCount(1)
        ->and($cli->commands[0])->toBe([
            'jpegoptim', '-q', '--strip-com', '--strip-iptc', '--strip-xmp', '--all-progressive', '--max=85', $output,
        ])
        ->and($cli->commands[0])->not->toContain('--strip-exif')
        ->and($cli->commands[0])->not->toContain('--strip-all');

    @unlink($input);
    @unlink($output);
});

test('jpeg levels map to the jpegoptim max', function (string $level, ?string $maxFlag) {
    $cli = new FakeCli(['jpegoptim']);

    [, $input, $output] = compressWithFakeCli($cli, 'jpg', 'image/jpeg', $level);

    $flags = array_filter($cli->commands[0], fn ($arg) => str_starts_with($arg, '--max='));
    expect(array_values($flags))->toBe($maxFlag === null ? [] : [$maxFlag]);

    @unlink($input);
    @unlink($output);
})->with([
    'light is lossless' => ['light', null],
    'strong' => ['strong', '--max=75'],
]);

test('png uses pngquant with the recommended quality', function () {
    $cli = new FakeCli(['pngquant', 'oxipng']);

    [, $input, $output] = compressWithFakeCli($cli, 'png', 'image/png');

    expect($cli->commands)->toHaveCount(1)
        ->and($cli->commands[0])->toBe([
            'pngquant', '--quality=80-95', '--speed', '3', '--strip', '--force', '--output', $output, $input,
        ]);

    @unlink($input);
    @unlink($output);
});

test('png falls back to lossless oxipng when pngquant cannot meet the quality (exit 99)', function () {
    $cli = new FakeCli(['pngquant', 'oxipng'], ['pngquant' => 99]);

    [, $input, $output] = compressWithFakeCli($cli, 'png', 'image/png');

    expect($cli->commands)->toHaveCount(2)
        ->and($cli->commands[1][0])->toBe('oxipng')
        ->and($cli->commands[1])->toContain('--strip', 'safe');

    @unlink($input);
    @unlink($output);
});

test('png light level uses only oxipng', function () {
    $cli = new FakeCli(['pngquant', 'oxipng']);

    [, $input, $output] = compressWithFakeCli($cli, 'png', 'image/png', 'light');

    expect(array_column($cli->commands, 0))->toBe(['oxipng']);

    @unlink($input);
    @unlink($output);
});

test('webp uses cwebp with the level quality and method 4', function () {
    $cli = new FakeCli(['cwebp']);

    [, $input, $output] = compressWithFakeCli($cli, 'webp', 'image/webp');

    expect($cli->commands[0])->toBe(['cwebp', '-quiet', '-q', '80', '-m', '4', '-metadata', 'icc,exif', $input, '-o', $output]);

    @unlink($input);
    @unlink($output);
});

test('without any CLI the service still returns a valid image for every format', function (string $extension, string $mime) {
    [$bytes, $input, $output] = compressWithFakeCli(new FakeCli, $extension, $mime);

    expect($bytes)->toBeGreaterThan(0)
        ->and(getimagesize($output)['mime'])->toBe($mime);

    @unlink($input);
    @unlink($output);
})->with([
    ['jpg', 'image/jpeg'],
    ['png', 'image/png'],
    ['webp', 'image/webp'],
]);

test('a crashing CLI falls back to the built-in method and still returns a valid image', function () {
    $cli = new FakeCli(['jpegoptim', 'cwebp'], ['jpegoptim' => 1, 'cwebp' => -1]);

    [$jpegBytes, $jpegIn, $jpegOut] = compressWithFakeCli($cli, 'jpg', 'image/jpeg');
    [$webpBytes, $webpIn, $webpOut] = compressWithFakeCli($cli, 'webp', 'image/webp');

    expect($jpegBytes)->toBeGreaterThan(0)
        ->and(getimagesize($jpegOut)['mime'])->toBe('image/jpeg')
        ->and($webpBytes)->toBeGreaterThan(0)
        ->and(getimagesize($webpOut)['mime'])->toBe('image/webp');

    array_map('unlink', [$jpegIn, $jpegOut, $webpIn, $webpOut]);
});

test('an unsupported mime type throws even when every CLI is available', function () {
    $cli = new FakeCli(['jpegoptim', 'pngquant', 'oxipng', 'cwebp']);
    $input = makeTempInputFile('jpg');

    expect(fn () => (new ImageCompressService($cli))->compress($input, sys_get_temp_dir().'/x.gif', 'image/gif'))
        ->toThrow(InvalidArgumentException::class);

    expect($cli->commands)->toBeEmpty();

    @unlink($input);
});

test('a large png uses the fast pngquant speed and the light oxipng level', function () {
    config()->set('image_tools.compression.png.large_pixels', 1);
    $quant = new FakeCli(['pngquant', 'oxipng']);
    $lossless = new FakeCli(['pngquant', 'oxipng'], ['pngquant' => 99]);

    [, $in1, $out1] = compressWithFakeCli($quant, 'png', 'image/png');
    [, $in2, $out2] = compressWithFakeCli($lossless, 'png', 'image/png');

    expect($quant->commands[0])->toContain('--speed', '10')
        ->and($lossless->commands[1])->toContain('-o', '1');

    array_map('unlink', [$in1, $out1, $in2, $out2]);
});

test('a png too large for lossless recompression is returned unchanged when pngquant cannot meet the quality', function () {
    config()->set('image_tools.compression.png.oxipng_max_pixels', 1);
    $cli = new FakeCli(['pngquant', 'oxipng'], ['pngquant' => 99]);

    [$bytes, $input, $output] = compressWithFakeCli($cli, 'png', 'image/png');

    expect(array_column($cli->commands, 0))->toBe(['pngquant'])
        ->and($bytes)->toBe(filesize($input))
        ->and(file_get_contents($output))->toBe(file_get_contents($input));

    @unlink($input);
    @unlink($output);
});

test('a timed-out CLI falls straight back to the built-in method without trying the next CLI', function () {
    $cli = new FakeCli(['pngquant', 'oxipng'], ['pngquant' => -1]);

    [$bytes, $input, $output] = compressWithFakeCli($cli, 'png', 'image/png');

    expect(array_column($cli->commands, 0))->toBe(['pngquant'])
        ->and($bytes)->toBeGreaterThan(0)
        ->and(getimagesize($output)['mime'])->toBe('image/png');

    @unlink($input);
    @unlink($output);
});

test('the request deadline caps every CLI timeout, and an expired deadline stops the work', function () {
    $cli = new FakeCli(['jpegoptim']);
    $input = makeTempInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('deadline-', true).'.jpg';

    $service = (new ImageCompressService($cli))->deadlineAt(microtime(true) + 30);
    $service->compress($input, $output, 'image/jpeg');

    expect($cli->timeouts[0])->toBeLessThanOrEqual(30)->toBeGreaterThan(0);

    $expired = (new ImageCompressService($cli))->deadlineAt(microtime(true) - 1);

    expect(fn () => $expired->compress($input, $output, 'image/jpeg'))->toThrow(RuntimeException::class);

    @unlink($input);
    @unlink($output);
});

$realCli = new CliRunner;

test('real pngquant shrinks a flat-color screenshot to well under its original size', function () {
    $gdImage = imagecreatetruecolor(1200, 800);
    imagefilledrectangle($gdImage, 0, 0, 1200, 800, imagecolorallocate($gdImage, 245, 245, 245));
    for ($i = 0; $i < 40; $i++) {
        imagefilledrectangle($gdImage, 20 + $i * 25, 100 + ($i % 7) * 60, 40 + $i * 25, 140 + ($i % 7) * 60, imagecolorallocate($gdImage, 30 + $i * 5, 64, 175 - $i * 3));
    }
    $input = sys_get_temp_dir().'/'.uniqid('real-pngquant-', true).'.png';
    imagepng($gdImage, $input, 1);
    imagedestroy($gdImage);
    $output = sys_get_temp_dir().'/'.uniqid('real-pngquant-out-', true).'.png';

    $bytes = (new ImageCompressService)->compress($input, $output, 'image/png');

    expect($bytes)->toBeLessThan(filesize($input) * 0.6)
        ->and(getimagesize($output)['mime'])->toBe('image/png');

    @unlink($input);
    @unlink($output);
})->skip(! $realCli->has('pngquant'), 'pngquant が無い環境');

test('real jpegoptim keeps the EXIF orientation and the ICC profile', function () {
    $input = base_path('tests/fixtures/orient6.jpg');
    $output = sys_get_temp_dir().'/'.uniqid('real-jpegoptim-out-', true).'.jpg';

    (new ImageCompressService)->compress($input, $output, 'image/jpeg', 'light');

    $exif = exif_read_data($output);
    expect($exif['Orientation'] ?? null)->toBe(6)
        ->and(strpos(file_get_contents($output), 'ICC_PROFILE'))->not->toBeFalse();

    @unlink($output);
})->skip(! $realCli->has('jpegoptim') || ! function_exists('exif_read_data'), 'jpegoptim か exif 拡張が無い環境');
