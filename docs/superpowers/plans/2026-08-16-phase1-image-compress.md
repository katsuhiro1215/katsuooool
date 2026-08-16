# Phase 1 画像圧縮ツール Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** katsuooool の最初の実ツールとして、JPEG/PNG/WebP画像を自動圧縮してダウンロードできる `/image-compress` ページを実装する。

**Architecture:** Phase 0 で作った共通基盤(`ToolUploadService`・`ToolDownloadService`・`ToolEventLogger`)の上に、画像圧縮専用の `ImageCompressService`(Intervention Image、GD/Imagickをランタイム検出)と `ImageCompressController` を追加する。フロントエンドは Inertia ページ+素の Tailwind CSS で実装し、圧縮処理自体は `axios` を使ったJSON APIとして呼び出す(ページ遷移を伴わない結果表示のため)。

**Tech Stack:** PHP 8.3 / Laravel 13 / Intervention Image 3.11 / Pest 4 / Inertia.js v3 / Vue 3 + TypeScript / Tailwind CSS

**Spec:** `/Users/katsuhiro.k1215/Project/katsuooool/SPEC.md`(セクション 7)/ `TASKS.md`(Phase 1)

## Global Constraints

- 許可形式は JPEG / PNG / WebP のみ(SPEC.md §5, §7)。
- 上限値: 1ファイル10MB、1回の処理で最大10ファイル(SPEC.md §5)。`ToolUploadService::store()` の `maxFileBytes`/`maxFileCount` にこの値を渡す。
- 圧縮パラメータは固定値(JPEG/WebP品質80相当)で自動最適化のみ。ユーザーが調整するUIは持たない(SPEC.md §7)。
- 複数枚は ZIP で一括ダウンロード、1枚は単体ダウンロード(SPEC.md §7)。
- 新規ツール画面は shadcn-vue を使わず、Tailwind CSS で独自実装する(CLAUDE.md「UI方針」)。デザインは `resources/js/pages/Home.vue` のトーン(白基調・最小限の装飾)に合わせる。
- 本番は Xserver共有サーバー(`exec()`・シェルコマンド実行不可)。画像処理は Intervention Image の GD/Imagickドライバのみで完結させ、CLIバイナリに依存しない。
- 一時ファイルの保存規約は既存の `tool-tmp/{batchId}/...` に従う(Task 9 のprune処理が再帰的に対象ディレクトリを削除するため、新しいサブディレクトリを切っても自動的に削除対象になる)。
- 個別ファイルの失敗はスキップし、成功分のみ結果に含める。失敗理由を返す(SPEC.md §7)。

---

## Task 1: ImageCompressService(圧縮ロジック)

**Files:**
- Create: `app/Services/Tools/ImageCompressService.php`
- Test: `tests/Feature/Tools/ImageCompressServiceTest.php`

**Interfaces:**
- Produces: `App\Services\Tools\ImageCompressService::compress(string $inputPath, string $outputPath, string $mimeType): int`
  - `$mimeType` は `'image/jpeg'`, `'image/png'`, `'image/webp'` のいずれか。それ以外は `InvalidArgumentException` を投げる。
  - 戻り値は圧縮後のバイト数。
  - GD/Imagickはランタイムで検出(`extension_loaded('imagick')` なら Imagick、そうでなければ GD)。

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/ImageCompressServiceTest.php`:

```php
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
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=ImageCompressServiceTest`
Expected: FAIL(クラス `App\Services\Tools\ImageCompressService` が存在しない)

- [ ] **Step 3: サービスを実装**

`app/Services/Tools/ImageCompressService.php`:

```php
<?php

namespace App\Services\Tools;

use InvalidArgumentException;
use Intervention\Image\ImageManager;

class ImageCompressService
{
    private const JPEG_QUALITY = 80;

    private const WEBP_QUALITY = 80;

    public function compress(string $inputPath, string $outputPath, string $mimeType): int
    {
        $image = $this->makeManager()->read($inputPath);

        $encoded = match ($mimeType) {
            'image/jpeg' => $image->toJpeg(quality: self::JPEG_QUALITY),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: self::WEBP_QUALITY),
            default => throw new InvalidArgumentException("Unsupported mime type: {$mimeType}"),
        };

        $encoded->save($outputPath);

        return $encoded->size();
    }

    private function makeManager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick()
            : ImageManager::gd();
    }
}
```

- [ ] **Step 4: テストを再実行**

Run: `php artisan test --filter=ImageCompressServiceTest`
Expected: PASS(4 tests)

- [ ] **Step 5: コミット**

```bash
git add app/Services/Tools/ImageCompressService.php tests/Feature/Tools/ImageCompressServiceTest.php composer.json composer.lock
git commit -m "feat: add ImageCompressService (GD/Imagick auto-detected)"
```

Note: `composer.json`/`composer.lock` は `intervention/image` の依存追加を含む(既に `composer require intervention/image:^3.0` 実行済み。差分をこのコミットに含める)。

---

## Task 2: ImageCompressController・ルーティング・イベントログ連携

**Files:**
- Create: `app/Http/Controllers/Tools/ImageCompressController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Tools/ImageCompressControllerTest.php`

**Interfaces:**
- Consumes: `ToolUploadService::store()`(Phase 0)、`ImageCompressService::compress()`(Task 1)、`ToolDownloadService::zip()`/`signedUrl()`(Phase 0)、`ToolEventLogger::log()`(Phase 0)
- Produces:
  - `GET /image-compress` → Inertia ページ `ImageCompress`(props なし)
  - `POST /image-compress` → JSON レスポンス:
    ```json
    {
      "results": [
        {
          "original_name": "photo.jpg",
          "status": "success",
          "input_bytes": 204800,
          "output_bytes": 102400,
          "reduction_percent": 50,
          "download_url": "https://.../tool-download/..."
        },
        {
          "original_name": "not-an-image.txt",
          "status": "rejected",
          "reason": "対応していないファイル形式です。"
        },
        {
          "original_name": "broken.jpg",
          "status": "failed",
          "reason": "画像の処理に失敗しました。"
        }
      ],
      "batch_download_url": "https://.../tool-download/..." または null(成功が2件未満の場合)
    }
    ```
  - ルート名: `image-compress.show`(GET)、`image-compress.process`(POST、`throttle:10,1` 適用)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/ImageCompressControllerTest.php`:

```php
<?php

use App\Models\ToolEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('the image-compress page renders', function () {
    $response = $this->get(route('image-compress.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageCompress'));
});

test('posting valid images returns compressed download links', function () {
    $files = [
        UploadedFile::fake()->image('photo-a.jpg', 800, 600),
        UploadedFile::fake()->image('photo-b.png', 400, 300),
    ];

    $response = $this->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.original_name', 'photo-a.jpg');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.status', 'success');
    $response->assertJsonPath('batch_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));

    expect(ToolEvent::where('tool_slug', 'image-compress')->where('event_type', 'process')->exists())->toBeTrue();
});

test('posting a single valid image does not return a batch download url', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 800, 600)];

    $response = $this->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('batch_download_url', null);
});

test('posting a disallowed file type is reported as rejected, not a fatal error', function () {
    $files = [
        UploadedFile::fake()->image('good.jpg', 800, 600),
        UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ];

    $response = $this->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.original_name', 'not-an-image.txt');
    $response->assertJsonPath('results.1.status', 'rejected');
});

test('posting more than 10 files is rejected with a validation error', function () {
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = $this->post(route('image-compress.process'), ['files' => $files]);

    $response->assertStatus(422);
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=ImageCompressControllerTest`
Expected: FAIL(ルート `image-compress.show`/`image-compress.process` が存在しない)

- [ ] **Step 3: コントローラーを実装**

`app/Http/Controllers/Tools/ImageCompressController.php`:

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\ImageCompressService;
use App\Services\Tools\ToolDownloadService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\ToolUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ImageCompressController extends Controller
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_FILE_COUNT = 10;

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function show(): Response
    {
        return Inertia::render('ImageCompress');
    }

    public function process(
        Request $request,
        ToolUploadService $uploadService,
        ImageCompressService $compressService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $request->validate([
            'files' => ['required', 'array', 'max:'.self::MAX_FILE_COUNT],
            'files.*' => ['file'],
        ]);

        $uploadedFiles = $request->file('files');
        $originalNames = array_map(
            fn ($file) => $file->getClientOriginalName(),
            $uploadedFiles,
        );

        $uploadResult = $uploadService->store(
            uploadedFiles: $uploadedFiles,
            allowedMimeTypes: self::ALLOWED_MIME_TYPES,
            maxFileBytes: self::MAX_FILE_BYTES,
            maxFileCount: self::MAX_FILE_COUNT,
        );

        $eventLogger->log(
            toolSlug: 'image-compress',
            eventType: 'upload',
            data: ['file_count' => count($uploadResult->files)],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $disk = Storage::disk('local');
        $results = [];
        $successfulFiles = [];
        $totalInputBytes = 0;
        $totalOutputBytes = 0;

        // Note: $uploadResult->files is a sequentially re-indexed array (only
        // accepted files, in original order) — its keys do NOT correspond to
        // the original $uploadedFiles indices. $uploadResult->rejected keys
        // DO correspond to the original indices. These two index spaces must
        // never be mixed into the same $results array via array-key
        // assignment; append with $results[] instead, in each loop's own
        // natural order (successes first, then rejects).
        foreach ($uploadResult->files as $uploadedToolFile) {
            $extension = match ($uploadedToolFile->mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            };

            $outputRelativePath = "tool-tmp/{$uploadResult->batchId}/compressed/".basename($uploadedToolFile->path, '.'.pathinfo($uploadedToolFile->path, PATHINFO_EXTENSION)).'.'.$extension;

            try {
                $outputBytes = $compressService->compress(
                    inputPath: $disk->path($uploadedToolFile->path),
                    outputPath: $disk->path($outputRelativePath),
                    mimeType: $uploadedToolFile->mimeType,
                );
            } catch (Throwable) {
                $results[] = [
                    'original_name' => $uploadedToolFile->originalName,
                    'status' => 'failed',
                    'reason' => '画像の処理に失敗しました。',
                ];

                continue;
            }

            $totalInputBytes += $uploadedToolFile->size;
            $totalOutputBytes += $outputBytes;

            $downloadName = pathinfo($uploadedToolFile->originalName, PATHINFO_FILENAME).'.'.$extension;

            $successfulFiles[] = [
                'path' => $outputRelativePath,
                'download_name' => $downloadName,
            ];

            $results[] = [
                'original_name' => $uploadedToolFile->originalName,
                'status' => 'success',
                'input_bytes' => $uploadedToolFile->size,
                'output_bytes' => $outputBytes,
                'reduction_percent' => $uploadedToolFile->size > 0
                    ? (int) round((1 - ($outputBytes / $uploadedToolFile->size)) * 100)
                    : 0,
                'download_url' => $downloadService->signedUrl($outputRelativePath, $downloadName),
            ];
        }

        $rejected = $uploadResult->rejected;
        ksort($rejected);

        foreach ($rejected as $index => $reason) {
            $results[] = [
                'original_name' => $originalNames[$index] ?? '不明なファイル',
                'status' => 'rejected',
                'reason' => $reason,
            ];
        }

        $batchDownloadUrl = null;

        if (count($successfulFiles) >= 2) {
            $zipRelativePath = $downloadService->zip(
                $uploadResult->batchId,
                $successfulFiles,
                'compressed-images.zip',
            );

            $batchDownloadUrl = $downloadService->signedUrl($zipRelativePath, 'compressed-images.zip');
        }

        $eventLogger->log(
            toolSlug: 'image-compress',
            eventType: 'process',
            data: [
                'file_count' => count($successfulFiles),
                'input_bytes' => $totalInputBytes,
                'output_bytes' => $totalOutputBytes,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'results' => array_values($results),
            'batch_download_url' => $batchDownloadUrl,
        ]);
    }
}
```

- [ ] **Step 4: ルートを追加**

`routes/web.php` に追記(`tools.download` ルートの近くに追加)。ファイル冒頭の `use` 文に `use App\Http\Controllers\Tools\ImageCompressController;` を追加した上で:

```php
Route::get('/image-compress', [ImageCompressController::class, 'show'])->name('image-compress.show');

Route::post('/image-compress', [ImageCompressController::class, 'process'])
    ->middleware('throttle:10,1')
    ->name('image-compress.process');
```

- [ ] **Step 5: テストを再実行**

Run: `php artisan test --filter=ImageCompressControllerTest`
Expected: PASS(5 tests)

- [ ] **Step 6: コミット**

```bash
git add app/Http/Controllers/Tools/ImageCompressController.php routes/web.php tests/Feature/Tools/ImageCompressControllerTest.php
git commit -m "feat: add ImageCompressController with JSON compress endpoint"
```

---

## Task 3: フロントエンド(画像圧縮ページ)

**Files:**
- Create: `resources/js/pages/ImageCompress.vue`
- Test: 手動確認(Playwright、Step 6を参照)。自動テストは追加しない(バックエンドは Task 1・2 でカバー済み。UIの自動テストはPhase 1の対象外)。

**Interfaces:**
- Consumes: `POST /image-compress` の JSON レスポンス(Task 2 で定義した形)
- Produces: `/image-compress` ページ(Inertia コンポーネント名 `ImageCompress`)

- [ ] **Step 1: axios の CSRF 動作を確認**

`resources/js/bootstrap.ts` は既に `window.axios = axios;` と `X-Requested-With` ヘッダを設定済み。Laravel は同一オリジンのリクエストで `XSRF-TOKEN` Cookie を自動送出し、axios はデフォルトで同一オリジン時に `X-XSRF-TOKEN` ヘッダへ自動反映する。追加設定は不要(この手順は確認のみ、コード変更なし)。

- [ ] **Step 2: ページコンポーネントを実装**

`resources/js/pages/ImageCompress.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

interface SuccessResult {
    original_name: string;
    status: 'success';
    input_bytes: number;
    output_bytes: number;
    reduction_percent: number;
    download_url: string;
}

interface RejectedOrFailedResult {
    original_name: string;
    status: 'rejected' | 'failed';
    reason: string;
}

type CompressResult = SuccessResult | RejectedOrFailedResult;

interface CompressResponse {
    results: CompressResult[];
    batch_download_url: string | null;
}

const MAX_FILES = 10;

const selectedFiles = ref<File[]>([]);
const isDragging = ref(false);
const isProcessing = ref(false);
const errorMessage = ref('');
const response = ref<CompressResponse | null>(null);

const canCompress = computed(() => selectedFiles.value.length > 0 && !isProcessing.value);

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;

    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(1)} MB`;
}

function addFiles(files: FileList | null) {
    if (!files) {
        return;
    }

    errorMessage.value = '';
    response.value = null;

    const incoming = Array.from(files);
    const combined = [...selectedFiles.value, ...incoming].slice(0, MAX_FILES);

    if (selectedFiles.value.length + incoming.length > MAX_FILES) {
        errorMessage.value = `一度にアップロードできるのは最大${MAX_FILES}枚までです。`;
    }

    selectedFiles.value = combined;
}

function onFileInputChange(event: Event) {
    const target = event.target as HTMLInputElement;
    addFiles(target.files);
    target.value = '';
}

function onDrop(event: DragEvent) {
    isDragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
}

function removeFile(index: number) {
    selectedFiles.value = selectedFiles.value.filter((_, i) => i !== index);
}

async function compress() {
    if (!canCompress.value) {
        return;
    }

    isProcessing.value = true;
    errorMessage.value = '';
    response.value = null;

    const formData = new FormData();
    selectedFiles.value.forEach((file) => {
        formData.append('files[]', file);
    });

    try {
        const { data } = await axios.post<CompressResponse>('/image-compress', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        response.value = data;
    } catch {
        errorMessage.value = '圧縮処理に失敗しました。時間をおいて再度お試しください。';
    } finally {
        isProcessing.value = false;
    }
}

function isSuccess(result: CompressResult): result is SuccessResult {
    return result.status === 'success';
}
</script>

<template>
    <Head title="画像圧縮 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700">← トップに戻る</a>
                <h1 class="mt-2 text-2xl font-bold text-gray-900">画像圧縮</h1>
                <p class="mt-2 text-sm text-gray-600">
                    JPEG・PNG・WebP画像を自動で最適化します。1ファイル10MBまで、最大{{ MAX_FILES }}枚まで一度に処理できます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="rounded-lg border-2 border-dashed p-10 text-center transition"
                :class="isDragging ? 'border-gray-400 bg-gray-100' : 'border-gray-300 bg-white'"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
            >
                <p class="text-sm text-gray-600">ここに画像をドラッグ&ドロップ、または</p>
                <label
                    class="mt-3 inline-block cursor-pointer rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                >
                    ファイルを選択
                    <input
                        type="file"
                        class="hidden"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        @change="onFileInputChange"
                    />
                </label>
            </div>

            <p v-if="errorMessage" class="mt-4 text-sm text-red-600">{{ errorMessage }}</p>

            <ul v-if="selectedFiles.length > 0" class="mt-6 space-y-2">
                <li
                    v-for="(file, index) in selectedFiles"
                    :key="`${file.name}-${index}`"
                    class="flex items-center justify-between rounded-md border border-gray-200 bg-white px-4 py-2 text-sm"
                >
                    <span class="truncate text-gray-800">{{ file.name }}</span>
                    <button type="button" class="ml-4 text-gray-400 hover:text-red-600" @click="removeFile(index)">削除</button>
                </li>
            </ul>

            <button
                type="button"
                class="mt-6 w-full rounded-md bg-gray-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                :disabled="!canCompress"
                @click="compress"
            >
                {{ isProcessing ? '圧縮しています...' : '圧縮する' }}
            </button>

            <div v-if="response" class="mt-8">
                <div v-if="response.batch_download_url" class="mb-4">
                    <a
                        :href="response.batch_download_url"
                        class="inline-block rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        まとめてダウンロード(ZIP)
                    </a>
                </div>

                <ul class="space-y-2">
                    <li
                        v-for="(result, index) in response.results"
                        :key="`${result.original_name}-${index}`"
                        class="rounded-md border border-gray-200 bg-white px-4 py-3 text-sm"
                    >
                        <template v-if="isSuccess(result)">
                            <div class="flex items-center justify-between">
                                <span class="truncate text-gray-800">{{ result.original_name }}</span>
                                <a :href="result.download_url" class="text-gray-900 underline hover:text-gray-600">ダウンロード</a>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ formatBytes(result.input_bytes) }} → {{ formatBytes(result.output_bytes) }}
                                ({{ result.reduction_percent }}% 削減)
                            </p>
                        </template>
                        <template v-else>
                            <div class="flex items-center justify-between">
                                <span class="truncate text-gray-500">{{ result.original_name }}</span>
                                <span class="text-xs text-red-600">{{ result.reason }}</span>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 3: 型・Lintチェック**

Run: `npx vue-tsc --noEmit && npm run lint:check`
Expected: エラーなし

- [ ] **Step 4: config/tools.php の href を確認**

`config/tools.php` の `image-compress` エントリの `href` は既に `/image-compress` になっている(Phase 0 で設定済み)ため変更不要。念のため確認する:

Run: `grep -A5 "image-compress" config/tools.php`
Expected: `'href' => '/image-compress',` が含まれる

- [ ] **Step 5: コミット**

```bash
git add resources/js/pages/ImageCompress.vue
git commit -m "feat: add ImageCompress page (drag & drop upload, results, downloads)"
```

- [ ] **Step 6: 実際の画面をPlaywrightで確認する(ユーザーへスクリーンショット送付)**

このステップはコード変更を伴わない、視覚的な動作確認。

1. `npm run build` を実行し、本番ビルドが通ることを確認する。
2. `php artisan serve` (または `composer dev`)でローカルサーバーを起動する。
3. Playwright で以下を確認しスクリーンショットを撮る:
   - `http://127.0.0.1:8000/` を開き、トップページのツールカードから「画像圧縮」へ遷移できることを確認
   - `http://127.0.0.1:8000/image-compress` を開き、ドラッグ&ドロップ領域とファイル選択ボタンが表示されることを確認しスクリーンショット
   - テスト画像(適当なJPEG)をファイル選択で1枚アップロードし、「圧縮する」ボタンを押して結果(圧縮前後サイズ・削減率・ダウンロードリンク)が表示されることを確認しスクリーンショット
   - 2枚以上アップロードした場合に「まとめてダウンロード(ZIP)」ボタンが表示されることを確認しスクリーンショット
4. スクリーンショットをユーザーに送付し、UIの見た目について確認を取る。
5. 問題があれば `resources/js/pages/ImageCompress.vue` を修正し、Step 3 のチェックを再実行してから再度コミットする。

---

## 完了確認(Phase 1 全体)

- [ ] `composer test`(Pint + PHPStan + Pest)がグリーン
- [ ] `npm run lint:check && npm run format:check && npm run types:check` がグリーン
- [ ] `npm run build` が成功する
- [ ] Playwrightでの実画面確認(Step 6)をユーザーに提示し、フィードバックを反映済み
- [ ] TASKS.md の Phase 1 チェックリストを更新する
