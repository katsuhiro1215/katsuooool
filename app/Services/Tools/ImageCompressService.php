<?php

namespace App\Services\Tools;

use App\Support\ImageToolLimits;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;

/**
 * 画像の圧縮。CLI（jpegoptim・pngquant・oxipng・cwebp）で行い、
 * CLI が無い・失敗した・時間切れのときは Intervention（Imagick/GD）の方式に戻す。
 * 比較の根拠: docs/superpowers/specs/2026-10-11-m5-2-compression-design.md
 *
 * @phpstan-type Settings array{jpeg_max: int|null, png_quality: string|null, webp_quality: int}
 */
class ImageCompressService
{
    private const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly CliRunner $cli = new CliRunner) {}

    public function compress(string $inputPath, string $outputPath, string $mimeType, string $level = 'recommended'): int
    {
        if (! in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported mime type: {$mimeType}");
        }

        $settings = $this->settingsFor($level);

        $done = match ($mimeType) {
            'image/jpeg' => $this->jpegWithCli($inputPath, $outputPath, $settings),
            'image/png' => $this->pngWithCli($inputPath, $outputPath, $settings),
            'image/webp' => $this->webpWithCli($inputPath, $outputPath, $settings),
        };

        if ($done && is_file($outputPath) && filesize($outputPath) > 0) {
            return (int) filesize($outputPath);
        }

        return $this->compressWithBuiltIn($inputPath, $outputPath, $mimeType, $settings);
    }

    /**
     * @return Settings
     */
    private function settingsFor(string $level): array
    {
        $levels = config('image_tools.compression.levels');

        return $levels[$level] ?? $levels[config('image_tools.compression.default_level')];
    }

    private function timeout(): int
    {
        return (int) config('image_tools.compression.cli_timeout', 120);
    }

    /**
     * jpegoptim。EXIF（向き）と ICC を残すため、--strip-exif・--strip-all は使わない。
     * --max は品質の上限で、元より高い品質には上げない（＝大きくならない）。
     *
     * @param  Settings  $settings
     */
    private function jpegWithCli(string $input, string $output, array $settings): bool
    {
        if (! $this->cli->has('jpegoptim') || ! copy($input, $output)) {
            return false;
        }

        $command = ['jpegoptim', '-q', '--strip-com', '--strip-iptc', '--strip-xmp', '--all-progressive'];

        if ($settings['jpeg_max'] !== null) {
            $command[] = '--max='.$settings['jpeg_max'];
        }

        $command[] = $output;

        return $this->succeeded($this->cli->run($command, $this->timeout()), $output);
    }

    /**
     * pngquant（品質の範囲を満たせなければ終了コード 98/99）→ oxipng（無劣化）の順。
     *
     * @param  Settings  $settings
     */
    private function pngWithCli(string $input, string $output, array $settings): bool
    {
        if ($settings['png_quality'] !== null && $this->cli->has('pngquant')) {
            $code = $this->cli->run([
                'pngquant', '--quality='.$settings['png_quality'], '--speed', '3', '--strip', '--force', '--output', $output, $input,
            ], $this->timeout());

            if ($this->succeeded($code, $output)) {
                return true;
            }
        }

        if (! $this->cli->has('oxipng')) {
            return false;
        }

        $code = $this->cli->run(['oxipng', '-o', '2', '--strip', 'safe', '-q', '--out', $output, $input], $this->timeout());

        // すでに最適化済みで、oxipng が出力を書かないことがある
        if ($code === 0 && ! is_file($output)) {
            copy($input, $output);
        }

        return $this->succeeded($code, $output);
    }

    /**
     * cwebp は WebP をそのまま入力にできる。
     *
     * @param  Settings  $settings
     */
    private function webpWithCli(string $input, string $output, array $settings): bool
    {
        if (! $this->cli->has('cwebp')) {
            return false;
        }

        $code = $this->cli->run(
            ['cwebp', '-quiet', '-q', (string) $settings['webp_quality'], '-m', '4', $input, '-o', $output],
            $this->timeout(),
        );

        return $this->succeeded($code, $output);
    }

    private function succeeded(int $exitCode, string $output): bool
    {
        if ($exitCode === 0 && is_file($output) && filesize($output) > 0) {
            return true;
        }

        // 98・99 は pngquant が品質の範囲を満たせなかっただけで、異常ではない
        if ($exitCode !== 0 && $exitCode !== 98 && $exitCode !== 99) {
            Log::warning('Image compression CLI failed; falling back.', ['exit_code' => $exitCode]);
        }

        @unlink($output);

        return false;
    }

    /**
     * CLI が使えないときの方式（Intervention。Imagick が無ければ GD）。
     *
     * @param  Settings  $settings
     */
    private function compressWithBuiltIn(string $inputPath, string $outputPath, string $mimeType, array $settings): int
    {
        $image = $this->makeManager()->read($inputPath);

        $encoded = match ($mimeType) {
            'image/jpeg' => $image->toJpeg(quality: $settings['jpeg_max'] ?? 90),
            'image/png' => $this->encodeSmallestPng($image),
            'image/webp' => $image->toWebp(quality: $settings['webp_quality']),
            default => throw new InvalidArgumentException("Unsupported mime type: {$mimeType}"),
        };

        $encoded->save($outputPath);

        return $encoded->size();
    }

    /**
     * A truecolor PNG re-encode frequently doesn't shrink already-optimized
     * PNGs (or even grows them slightly), while palette (indexed) encoding
     * shrinks most UI/screenshot-style PNGs significantly at the cost of
     * capping the image to 255 colors. Encoding both and keeping whichever
     * is smaller gets the size benefit without a fixed quality trade-off.
     * （CLI が無いときの経路。pngquant のほうが速く小さいため、通常は使われない）
     */
    private function encodeSmallestPng(ImageInterface $image): EncodedImageInterface
    {
        $truecolor = $image->toPng();

        // 256色化は画素数に比例して遅くなる（2,500万画素で約265秒）ため、大きい画像では省く
        if ($image->width() * $image->height() > ImageToolLimits::pngIndexedMaxPixels()) {
            return $truecolor;
        }

        $indexed = $image->toPng(indexed: true);

        return $indexed->size() <= $truecolor->size() ? $indexed : $truecolor;
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
