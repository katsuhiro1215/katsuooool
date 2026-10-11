# M5-2 圧縮方式の改善 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 画像圧縮を CLI ツール（pngquant・oxipng・jpegoptim・cwebp）で行い、スクショが縮まない問題と WebP のメモリ不足を解消する。CLI が無い環境では現行の Imagick/GD にフォールバックする。あわせて AVIF の変換を `heic:speed=8` で約3倍速くする。

**Architecture:** `ImageCompressService` が、形式ごとに CLI を `CliRunner`（Symfony Process の薄いラッパー）経由で実行する。CLI が無い・失敗・時間切れのときは、現行の Intervention 方式に戻す。圧縮レベル（light／recommended／strong）は `config/image_tools.php` に置き、`level` パラメーターで選べる。

**Tech Stack:** Laravel 13 / Pest、Symfony Process、Intervention Image（Imagick/GD）、Alpine のパッケージ（pngquant・oxipng・jpegoptim・libwebp-tools）。

**Spec:** `docs/superpowers/specs/2026-10-11-m5-2-compression-design.md`

## Global Constraints

- ドキュメント・コメントは日本語。
- **`jpegoptim` に `--strip-exif`・`--strip-all` を使わない**（向き＝Orientation と ICC を守るため）。使うのは `--strip-com --strip-iptc --strip-xmp --all-progressive`（＋`--max=N`）のみ。
- 圧縮レベル: light（無劣化。JPEG は `--max` なし、PNG は oxipng、WebP は q90）／recommended（JPEG `--max=85`、PNG pngquant `80-95`、WebP q80）／strong（JPEG `--max=75`、PNG pngquant `65-80`、WebP q75）。既定は recommended。
- pngquant が終了コード 98（結果が元より大きい）・99（品質の下限を満たせない）を返したときは oxipng 無劣化に落とす。それも使えなければ現行方式。
- CLI の実行時間の上限は 120 秒。超えたらフォールバック。
- 出力が元より大きいときに元を返す処理は、`ImageCompressController` にすでにある（変更しない）。
- M5-1 の枚数・サイズ・画素数・同時実行の制限はそのまま。
- 画面の変更（レベルの選択 UI、GPS の注記）は M5-3。
- 本番サーバーの操作はこの計画に含めない（実装後、オーナー確認のうえ別手順）。
- `composer test`（Pint・PHPStan・Pest）、`npx vitest run`、`npm run lint`、`npm run types:check` が通ること。
- この Mac には pngquant・oxipng・jpegoptim・Imagick が無い。CLI を実際に使うテストは、バイナリが無ければスキップし、Docker の比較用イメージで実行して確認する。

## Review Focus

- JPEG: 向き（Orientation=6）と ICC が圧縮後も残る（Task 2 の実 CLI テスト、Docker で実行）。`--strip-exif` が使われない（Task 2 のコマンド検証）。
- PNG: pngquant が 99 を返したら oxipng に落ちる。両方なければ現行方式で有効な PNG を返す（Task 2）。
- CLI が無い環境・CLI が異常終了した場合でも、有効な画像が返る（Task 2）。
- 未対応の MIME は、CLI があっても例外になる（Task 2）。
- `level` に不正な値を渡すと 422（Task 3）。
- WebP の入力（アルファあり・アニメーション）で CLI が失敗しても、現行方式に戻って処理が続く（Task 2 の異常終了テスト）。

---

## ファイル構成

| ファイル | 役割 |
|---|---|
| `app/Services/Tools/CliRunner.php`（新規） | 外部コマンドの有無の確認と実行（タイムアウト付き） |
| `app/Services/Tools/ImageCompressService.php`（変更） | 形式ごとの CLI 実行＋フォールバック＋レベル |
| `app/Services/Tools/ImageConvertService.php`（変更） | AVIF の `heic:speed` |
| `app/Http/Controllers/Tools/ImageCompressController.php`（変更） | `level` の受け取り |
| `config/image_tools.php`（変更） | 圧縮レベル、CLI のタイムアウト、AVIF の速度 |
| `Dockerfile.prod`（変更） | CLI パッケージの追加 |
| `tests/Support/FakeCli.php`（新規） | CLI の代役（実行したコマンドを記録し、出力ファイルを作る） |
| `tests/fixtures/orient6.jpg`（新規） | 向き=6・ICC つきの JPEG |

---

### Task 1: `CliRunner` と圧縮レベルの設定

**Files:**
- Create: `app/Services/Tools/CliRunner.php`, `tests/Support/FakeCli.php`
- Modify: `config/image_tools.php`
- Test: `tests/Feature/Tools/CliRunnerTest.php`

**Interfaces:**
- Produces:
  - `App\Services\Tools\CliRunner`（非 final）: `has(string $binary): bool`、`run(array $command, int $timeoutSeconds = 120): int`（終了コード。時間切れ・起動失敗は -1）
  - `Tests\Support\FakeCli extends CliRunner`: コンストラクター `__construct(array $available = [], array $exitCodes = [])`。`$commands`（実行されたコマンドの配列）を持つ。`has()` は `$available` に含まれるかを返す。`run()` はコマンドを記録し、終了コード（`$exitCodes[コマンド名]`、既定 0）を返す。0 のとき、`--output`・`--out`・`-o` の次の引数のパスに内容 `x` のファイルを作る。
  - 設定 `image_tools.compression.levels.{light|recommended|strong}`、`image_tools.compression.cli_timeout`、`image_tools.avif_speed`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/CliRunnerTest.php`:

```php
<?php

use App\Services\Tools\CliRunner;

test('has detects an existing and a missing binary', function () {
    $runner = new CliRunner;

    expect($runner->has('php'))->toBeTrue()
        ->and($runner->has('definitely-not-a-real-binary-xyz'))->toBeFalse();
});

test('run returns the exit code of the command', function () {
    $runner = new CliRunner;

    expect($runner->run([PHP_BINARY, '-r', 'exit(0);']))->toBe(0)
        ->and($runner->run([PHP_BINARY, '-r', 'exit(3);']))->toBe(3);
});

test('run returns -1 when the command times out', function () {
    $runner = new CliRunner;

    expect($runner->run([PHP_BINARY, '-r', 'sleep(5);'], 1))->toBe(-1);
});

test('run returns -1 when the binary cannot be started', function () {
    $runner = new CliRunner;

    expect($runner->run(['definitely-not-a-real-binary-xyz']))->toBe(-1);
});

test('compression levels are configured for light, recommended and strong', function () {
    $levels = config('image_tools.compression.levels');

    expect($levels)->toHaveKeys(['light', 'recommended', 'strong'])
        ->and($levels['recommended']['jpeg_max'])->toBe(85)
        ->and($levels['strong']['png_quality'])->toBe('65-80')
        ->and($levels['light']['jpeg_max'])->toBeNull()
        ->and($levels['light']['png_quality'])->toBeNull()
        ->and(config('image_tools.compression.cli_timeout'))->toBe(120)
        ->and(config('image_tools.avif_speed'))->toBe(8);
});
```

- [ ] **Step 2: 失敗を確認**

Run: `php artisan test tests/Feature/Tools/CliRunnerTest.php`
Expected: FAIL（`CliRunner` が存在しない）

- [ ] **Step 3: 実装**

`app/Services/Tools/CliRunner.php`:

```php
<?php

namespace App\Services\Tools;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * 画像圧縮などで使う外部コマンド（pngquant・jpegoptim など）の実行を包む。
 * テストでは差し替えられるよう、非 final にしている。
 */
class CliRunner
{
    public function has(string $binary): bool
    {
        return (new ExecutableFinder)->find($binary) !== null;
    }

    /**
     * @param  list<string>  $command
     * @return int 終了コード。時間切れや起動失敗は -1
     */
    public function run(array $command, int $timeoutSeconds = 120): int
    {
        // 存在しないコマンドは、起動に失敗したものとして -1 を返す（シェル経由の 127 にしない）
        if (! str_contains($command[0], DIRECTORY_SEPARATOR) && ! $this->has($command[0])) {
            return -1;
        }

        try {
            $process = new Process($command);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            return $process->getExitCode() ?? -1;
        } catch (ProcessTimedOutException) {
            return -1;
        } catch (Throwable) {
            return -1;
        }
    }
}
```

`tests/Support/FakeCli.php`:

```php
<?php

namespace Tests\Support;

use App\Services\Tools\CliRunner;

/**
 * CLI の代役。実行されたコマンドを記録し、成功時は出力先のファイルを作る。
 */
class FakeCli extends CliRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /**
     * @param  list<string>  $available  使えることにするコマンド名
     * @param  array<string, int>  $exitCodes  コマンド名ごとの終了コード（既定 0）
     */
    public function __construct(public array $available = [], public array $exitCodes = []) {}

    public function has(string $binary): bool
    {
        return in_array($binary, $this->available, true);
    }

    public function run(array $command, int $timeoutSeconds = 120): int
    {
        $this->commands[] = $command;
        $code = $this->exitCodes[$command[0]] ?? 0;

        if ($code === 0) {
            foreach (['--output', '--out', '-o'] as $flag) {
                $position = array_search($flag, $command, true);

                if ($position !== false && isset($command[$position + 1])) {
                    file_put_contents($command[$position + 1], 'x');
                }
            }
        }

        return $code;
    }
}
```

`config/image_tools.php` の `'concurrency' => 2,` の下に追加:

```php

    // AVIF のエンコード速度（0〜10。大きいほど速く、画質はわずかに下がる）。
    // 8 で約3倍速くなる（12MP: 9.3秒→3.3秒）。メモリは変わらない（2026-10-11 実測）
    'avif_speed' => 8,

    // 画像圧縮（M5-2）。CLI（pngquant・oxipng・jpegoptim・cwebp）で行う。
    // jpeg_max: jpegoptim の品質の上限（null は無劣化）／png_quality: pngquant の品質範囲（null は oxipng の無劣化）／webp_quality: cwebp の品質
    'compression' => [
        'default_level' => 'recommended',
        'cli_timeout' => 120,
        'levels' => [
            'light' => ['jpeg_max' => null, 'png_quality' => null, 'webp_quality' => 90],
            'recommended' => ['jpeg_max' => 85, 'png_quality' => '80-95', 'webp_quality' => 80],
            'strong' => ['jpeg_max' => 75, 'png_quality' => '65-80', 'webp_quality' => 75],
        ],
    ],
```

- [ ] **Step 4: 通過を確認**

Run: `php artisan test tests/Feature/Tools/CliRunnerTest.php`
Expected: PASS（5件）

- [ ] **Step 5: コミット**

```bash
git add app/Services/Tools/CliRunner.php tests/Support/FakeCli.php tests/Feature/Tools/CliRunnerTest.php config/image_tools.php
git commit -m "feat: 外部コマンドの実行を包む CliRunner と、圧縮レベルの設定を追加する(M5-2)"
```

---

### Task 2: `ImageCompressService` を CLI 方式にする

**Files:**
- Modify: `app/Services/Tools/ImageCompressService.php`
- Create: `tests/fixtures/orient6.jpg`
- Test: `tests/Feature/Tools/ImageCompressServiceTest.php`

**Interfaces:**
- Consumes: Task 1 の `CliRunner`・`FakeCli`・設定
- Produces: `ImageCompressService::__construct(CliRunner $cli = new CliRunner)`、`compress(string $inputPath, string $outputPath, string $mimeType, string $level = 'recommended'): int`（出力のバイト数）

- [ ] **Step 1: フィクスチャを作る**

向き=6 と ICC を持つ小さな JPEG（PIL を使う）:

```bash
python3 - <<'EOF'
from PIL import Image, ImageCms
im = Image.new('RGB', (300, 200), (200, 50, 50))
for x in range(0, 300, 10):
    for y in range(0, 200, 10):
        im.putpixel((x, y), (x % 255, y % 255, 128))
ex = im.getexif(); ex[0x0112] = 6
icc = ImageCms.ImageCmsProfile(ImageCms.createProfile('sRGB')).tobytes()
im.save('tests/fixtures/orient6.jpg', exif=ex, icc_profile=icc, quality=95)
EOF
ls -la tests/fixtures/orient6.jpg
```

- [ ] **Step 2: 失敗するテストを書く**

`tests/Feature/Tools/ImageCompressServiceTest.php` の既存の2つのテスト（`compress uses palette encoding …` と `compress skips palette encoding …`）は、CLI が無い環境の動作を確かめるものなので、`new ImageCompressService` を `new ImageCompressService(new FakeCli)` に変える（Docker など CLI がある環境でもフォールバック経路を通すため）。ファイル先頭の `use` に `use Tests\Support\FakeCli;` を足す。

末尾に追加:

```php
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

    expect($cli->commands[0])->toBe(['cwebp', '-quiet', '-q', '80', '-m', '4', $input, '-o', $output]);

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

$realCli = (new App\Services\Tools\CliRunner);

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
```

- [ ] **Step 3: 失敗を確認**

Run: `php artisan test tests/Feature/Tools/ImageCompressServiceTest.php`
Expected: FAIL（`ImageCompressService` のコンストラクターが `FakeCli` を受け取れない、コマンドが記録されない）

- [ ] **Step 4: 実装**

`app/Services/Tools/ImageCompressService.php` を次の内容に置き換える:

```php
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
     * @return array{jpeg_max: int|null, png_quality: string|null, webp_quality: int}
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
     * @param  array{jpeg_max: int|null, png_quality: string|null, webp_quality: int}  $settings
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
     * @param  array{jpeg_max: int|null, png_quality: string|null, webp_quality: int}  $settings
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
     * @param  array{jpeg_max: int|null, png_quality: string|null, webp_quality: int}  $settings
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

        if ($exitCode !== 0 && $exitCode !== 98 && $exitCode !== 99) {
            Log::warning('Image compression CLI failed; falling back to the built-in method.', ['exit_code' => $exitCode]);
        }

        @unlink($output);

        return false;
    }

    /**
     * CLI が使えないときの方式（Intervention。Imagick が無ければ GD）。
     *
     * @param  array{jpeg_max: int|null, png_quality: string|null, webp_quality: int}  $settings
     */
    private function compressWithBuiltIn(string $inputPath, string $outputPath, string $mimeType, array $settings): int
    {
        $image = $this->makeManager()->read($inputPath);

        $encoded = match ($mimeType) {
            'image/jpeg' => $image->toJpeg(quality: $settings['jpeg_max'] ?? 90),
            'image/png' => $this->encodeSmallestPng($image),
            'image/webp' => $image->toWebp(quality: $settings['webp_quality']),
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
```

- [ ] **Step 5: 通過を確認（Mac）**

Run: `vendor/bin/pint app tests >/dev/null; php artisan test tests/Feature/Tools/ImageCompressServiceTest.php tests/Feature/Tools/ImageCompressControllerTest.php`
Expected: PASS（実 CLI のテスト2件はスキップと表示される）

- [ ] **Step 6: コミット**

```bash
git add app/Services/Tools/ImageCompressService.php tests/Feature/Tools/ImageCompressServiceTest.php tests/fixtures/orient6.jpg
git commit -m "feat: 画像圧縮を CLI(pngquant・oxipng・jpegoptim・cwebp)で行い、無ければ現行方式に戻す(M5-2)"
```

---

### Task 3: `level` パラメーター

**Files:**
- Modify: `app/Http/Controllers/Tools/ImageCompressController.php`
- Test: `tests/Feature/Tools/ImageCompressControllerTest.php`

**Interfaces:**
- Consumes: Task 2 の `compress(..., string $level)`
- Produces: `POST /image-compress` が任意の `level`（`light`・`recommended`・`strong`）を受け取る。未指定は `recommended`。

- [ ] **Step 1: 失敗するテストを書く**

`ImageCompressControllerTest.php` の末尾に追加:

```php
test('an invalid compression level is rejected with a validation error', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 100, 100)];

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files, 'level' => 'extreme']);

    $response->assertStatus(422);
});

test('each valid compression level is accepted', function (string $level) {
    $files = [UploadedFile::fake()->image('photo.jpg', 100, 100)];

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files, 'level' => $level]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');
})->with(['light', 'recommended', 'strong']);
```

- [ ] **Step 2: 失敗を確認**

Run: `php artisan test tests/Feature/Tools/ImageCompressControllerTest.php --filter="compression level"`
Expected: FAIL（不正な `level` が 422 にならない）

- [ ] **Step 3: 実装**

`ImageCompressController::process()` の `$request->validate([...])` に追加:

```php
            'level' => ['sometimes', 'in:light,recommended,strong'],
```

`$compressService->compress(...)` の呼び出しに引数を足す:

```php
                    level: $request->string('level', config('image_tools.compression.default_level'))->toString(),
```

（既存の `validate` が `files` だけのときは、配列に `level` を追加する。）

- [ ] **Step 4: 通過を確認**

Run: `php artisan test tests/Feature/Tools/ImageCompressControllerTest.php`
Expected: PASS

- [ ] **Step 5: コミット**

```bash
git add app tests
git commit -m "feat: 画像圧縮に圧縮レベル(light・recommended・strong)を選べるようにする(M5-2)"
```

---

### Task 4: AVIF の変換を `heic:speed` で速くする

**Files:**
- Modify: `app/Services/Tools/ImageConvertService.php`
- Test: `tests/Feature/Tools/ImageConvertServiceTest.php`

**Interfaces:**
- Consumes: 設定 `image_tools.avif_speed`

- [ ] **Step 1: 失敗するテストを書く**

`ImageConvertServiceTest.php` の末尾に追加（Imagick が無い環境ではスキップ）:

```php
test('avif conversion sets the configured heic:speed on the Imagick image', function () {
    config()->set('image_tools.avif_speed', 8);
    $input = makeConvertTestInputFile('jpg');
    $output = sys_get_temp_dir().'/'.uniqid('avif-speed-', true).'.avif';

    $service = new ImageConvertService;
    $service->convert($input, $output, 'image/avif');

    expect(ImageConvertService::lastAvifSpeed())->toBe('8')
        ->and(filesize($output))->toBeGreaterThan(0);

    @unlink($input);
    @unlink($output);
})->skip(! class_exists(Imagick::class), 'Imagick 拡張が無い環境');
```

（`lastAvifSpeed()` は、直前の AVIF 変換で設定した速度を返す、テスト確認用の静的メソッド。）

- [ ] **Step 2: 失敗を確認**

Run（Docker の PHP で。この Mac は Imagick が無いためスキップになる）:

```bash
docker run --rm -v "$PWD":/var/www/html -w /var/www/html -e APP_ENV=testing --entrypoint php katsuooool-app-m51 artisan test tests/Feature/Tools/ImageConvertServiceTest.php --filter=heic
```

Expected: FAIL（`lastAvifSpeed` が存在しない）。`katsuooool-app-m51` が無い場合は `docker build -f Dockerfile.prod --target app -t katsuooool-app-m51 .` で作る。

- [ ] **Step 3: 実装**

`ImageConvertService.php`:

```php
use Intervention\Image\Interfaces\ImageInterface;

    private static ?string $lastAvifSpeed = null;

    /** テストで、直前の AVIF 変換に設定した速度を確かめるために使う */
    public static function lastAvifSpeed(): ?string
    {
        return self::$lastAvifSpeed;
    }
```

`match` の AVIF の行を置き換える:

```php
            'image/avif' => $this->encodeAvif($image),
```

メソッドを追加:

```php
    /**
     * AVIF のエンコード速度を上げる。8 で約3倍速い（12MP: 9.3秒→3.3秒）。メモリは変わらない。
     */
    private function encodeAvif(ImageInterface $image): \Intervention\Image\Interfaces\EncodedImageInterface
    {
        $native = $image->core()->native();

        if ($native instanceof \Imagick) {
            self::$lastAvifSpeed = (string) config('image_tools.avif_speed', 8);
            $native->setOption('heic:speed', self::$lastAvifSpeed);
        }

        return $image->toAvif(quality: self::AVIF_QUALITY);
    }
```

- [ ] **Step 4: 通過と速度を確認（Docker）**

```bash
docker run --rm -v "$PWD":/var/www/html -w /var/www/html -e APP_ENV=testing --entrypoint php katsuooool-app-m51 artisan test tests/Feature/Tools/ImageConvertServiceTest.php --filter=heic
```

Expected: PASS。続けて速度を実測する（12MP の JPEG を AVIF に変換。スクラッチパッドの `m52/corpus/photo-iphone-12mp.jpg` を使う）:

```bash
S=<scratchpad>/m52; docker run --rm --cpus=2 -m 768m -v "$PWD":/var/www/html -v "$S/corpus":/in:ro -w /var/www/html --entrypoint php katsuooool-app-m51 -d memory_limit=512M -r '
require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach ([0, 8] as $speed) { config(["image_tools.avif_speed" => $speed]); $t = microtime(true);
(new App\Services\Tools\ImageConvertService)->convert("/in/photo-iphone-12mp.jpg", "/tmp/o.avif", "image/avif");
printf("avif_speed=%d  %.1f秒\n", $speed, microtime(true) - $t); }'
```

Expected: `avif_speed=8` が `avif_speed=0`（既定）の 40% 以下の時間。

- [ ] **Step 5: コミット**

```bash
git add app tests
git commit -m "feat: AVIF のエンコード速度を上げる(heic:speed=8)(M5-2)"
```

---

### Task 5: Docker への CLI の追加・実環境での確認・仕様書

**Files:**
- Modify: `Dockerfile.prod`, `SPEC.md`, `CLAUDE.md`, `docs/superpowers/specs/2026-10-11-m5-2-compression-design.md`

- [ ] **Step 1: パッケージを追加**

`Dockerfile.prod` の `php-base` の `apk add --no-cache \ … imagemagick-jxl \` に続けて追加する（`imagemagick-jxl \` の次の行）:

```
        pngquant \
        oxipng \
        jpegoptim \
        libwebp-tools \
```

- [ ] **Step 2: ビルドして、実 CLI で全体のテストを通す**

```bash
docker build -f Dockerfile.prod --target app -t katsuooool-app-m52 . 2>&1 | tail -3
docker run --rm --entrypoint sh katsuooool-app-m52 -c 'for c in pngquant oxipng jpegoptim cwebp; do printf "%s: " $c; command -v $c; done'
docker run --rm -v "$PWD":/var/www/html -w /var/www/html -e APP_ENV=testing --entrypoint php katsuooool-app-m52 artisan test tests/Feature/Tools/ImageCompressServiceTest.php tests/Feature/Tools/ImageCompressControllerTest.php tests/Feature/Tools/CliRunnerTest.php
```

Expected: 4つの CLI のパスが表示され、テストはすべて PASS（実 CLI の2件がスキップされずに通る）。

- [ ] **Step 3: 本番相当のコンテナで、素材での結果を確認**

比較時の素材（スクラッチパッドの `m52/corpus/`）で、サービスを通した結果が比較の数値と一致するか確かめる。サービスの出力サイズが、元の何%かを表示する:

```bash
S=<scratchpad>/m52; docker run --rm --cpus=2 -m 768m -v "$PWD":/var/www/html -v "$S/corpus":/in:ro -w /var/www/html --entrypoint php katsuooool-app-m52 -d memory_limit=256M -r '
require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = new App\Services\Tools\ImageCompressService;
foreach ([["png-shot-tool.png","image/png"],["png-ai-3.png","image/png"],["photo-iphone-12mp.jpg","image/jpeg"],["photo-camera-18mp.jpg","image/jpeg"],["webp-photo-iphone-12mp.webp","image/webp"]] as [$f,$m]) {
 foreach (["light","recommended","strong"] as $lv) { $t=microtime(true); $b=$svc->compress("/in/$f","/tmp/out.bin",$m,$lv); $in=filesize("/in/$f");
 printf("%-30s %-12s %5.1f%%  %.1f秒\n",$f,$lv,100*$b/$in,microtime(true)-$t); } }'
```

Expected: 比較の表（設計書 §3）と同じ傾向。`png-shot-tool.png` が recommended で約28%、`strong` で約21%、`photo-camera-18mp.jpg` が元の100%以下。

- [ ] **Step 4: 仕様書を更新**

- `SPEC.md` §7（画像圧縮）に追記: 圧縮は CLI（pngquant・oxipng・jpegoptim・cwebp）で行い、無ければ Imagick/GD に戻す。`level`（light・recommended・strong）を受け取る（画面での選択は M5-3）。JPEG は EXIF（向き）と ICC を残す（位置情報も残る）。
- `CLAUDE.md` の技術スタック「画像処理」の行: 「CLI バイナリ(cwebp/pngquant等)には依存しない方針は維持」を「圧縮は CLI（pngquant・oxipng・jpegoptim・cwebp）を使い、無い環境は Imagick/GD にフォールバックする（2026-10-11、M5-2）」に改める。
- 設計書 `2026-10-11-m5-2-compression-design.md`: §3.2 と §4 の「現行は大きくなる」を「サービスは大きくなりうるが、コントローラーが元を返す」に直し、ステータスを「実装済み」にする。

- [ ] **Step 5: 全体の確認とコミット**

Run: `composer test && npx vitest run && npm run lint && npm run types:check`
Expected: すべて PASS

```bash
git add Dockerfile.prod SPEC.md CLAUDE.md docs
git commit -m "feat: 本番イメージに圧縮用の CLI を追加し、仕様書を更新する(M5-2)"
```

---

## 本番反映

この計画には含めない。実装とレビューのあと、オーナー確認のうえ別手順。`Dockerfile.prod` のパッケージが増えるため、`build app`・`build katsuooool-web` のあと `app`・`scheduler`・`katsuooool-web` を再作成する（M5-1 と同じ）。反映後は、実画像（スクショ・写真・WebP）で圧縮率とメモリを確かめる。
