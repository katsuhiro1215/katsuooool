# M9-2 UI パターンのデータ拡充 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 空の 11 カテゴリとページネーションに、約 110 件の UI パターン（HTML/CSS）を、JSON と取り込みコマンドで追加する。

**Architecture:** データは `database/data/ui-patterns/<カテゴリ>.json`。`ui-patterns:import` が冪等に取り込む（同じカテゴリ・タイトルは html/css だけ更新、なければ追加。`copy_count`・`is_premium`・JSON にない行は触れない）。`UiPatternDataTest` が全ファイルを決まりに沿って検査し、ブラウザで全件を確認する。

**Tech Stack:** Laravel 13（Artisan コマンド）、Pest、Playwright（ローカル確認）。

**Spec:** `docs/superpowers/specs/2026-10-12-m9-2-pattern-data-design.md`

## Global Constraints

- ドキュメント・コメントは日本語。タイトルは「日本語名（English Name）」、255 文字以内、同一ファイル内で重複なし。
- 外部依存なし：URL は `https://placehold.co/` のみ可。`<script>`・`position: fixed`・`vh`/`vw` 単位・`@import` は使わない。動きは CSS のみ。
- モーダル・トーストは画面に固定せず、部品そのものを静的に置く。
- 全件 `is_premium` は false（取り込みで既存行の `is_premium`・`copy_count` は変えない）。
- 管理画面・既存の画面は変えない（確認で崩れが見つかった場合のみ修正）。
- 本番反映は M9-1 とあわせて後でまとめて行う（このブランチでは行わない）。

## Review Focus

- 取り込みを 2 回実行しても件数が増えず、既存行の `copy_count`・`is_premium` が壊れない → Task 1。
- JSON が壊れている、カテゴリ名が不正、必須項目がない場合に、途中まで書き込まれない → Task 1。
- 管理画面から登録された、JSON にないタイトルの行が消えない・変わらない → Task 1。
- 全 JSON が「外部依存・script・fixed・vh/vw」の決まりを守る → Task 1 の `UiPatternDataTest`。
- 各カテゴリの全件が、プレビューの枠で切れず・読める → Task 2〜12 のブラウザ確認。

---

### Task 1: 取り込みコマンドと検査テスト

**Files:**
- Create: `app/Console/Commands/ImportUiPatternsCommand.php`
- Create: `tests/Feature/Console/ImportUiPatternsCommandTest.php`
- Create: `tests/Feature/UiPatternDataTest.php`
- Create: `database/data/ui-patterns/.gitkeep`

**Interfaces:**
- Produces: `php artisan ui-patterns:import {category?} {--path=}`（終了コード 0＝成功、1＝検証エラー）、データの形式 `[{"title","html","css"}]`。後続のタスクは、この形式の JSON を `database/data/ui-patterns/<category>.json` に置く。

- [ ] **Step 1: 失敗するテストを書く（取り込みコマンド）**

`tests/Feature/Console/ImportUiPatternsCommandTest.php`:

```php
<?php

use App\Models\UiPatternSnippet;
use Illuminate\Support\Facades\File;

function makeDataDir(array $files): string
{
    $dir = sys_get_temp_dir().'/ui-patterns-'.uniqid();
    File::makeDirectory($dir);

    foreach ($files as $name => $content) {
        File::put("$dir/$name", is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE));
    }

    return $dir;
}

function pattern(string $title, string $html = '<p>x</p>', string $css = 'p{color:#333}'): array
{
    return ['title' => $title, 'html' => $html, 'css' => $css];
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/ui-patterns-*') ?: [] as $dir) {
        File::deleteDirectory($dir);
    }
});

test('新しいパターンを追加し、件数を表示する', function () {
    $dir = makeDataDir(['modal.json' => [pattern('シンプルモーダル（Simple Modal）'), pattern('確認モーダル（Confirm Modal）')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])
        ->expectsOutputToContain('追加 2 件・更新 0 件')
        ->assertExitCode(0);

    expect(UiPatternSnippet::where('category', 'modal')->count())->toBe(2)
        ->and(UiPatternSnippet::first()->is_premium)->toBeFalse()
        ->and(UiPatternSnippet::first()->copy_count)->toBe(0);
});

test('2 回実行しても件数は増えず、更新として扱う', function () {
    $dir = makeDataDir(['modal.json' => [pattern('シンプルモーダル（Simple Modal）')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])->assertExitCode(0);
    $this->artisan('ui-patterns:import', ['--path' => $dir])
        ->expectsOutputToContain('追加 0 件・更新 1 件')
        ->assertExitCode(0);

    expect(UiPatternSnippet::count())->toBe(1);
});

test('既存行は html と css だけ更新し、copy_count と is_premium は変えない', function () {
    UiPatternSnippet::create([
        'category' => 'modal', 'title' => 'シンプルモーダル（Simple Modal）',
        'html' => '<p>old</p>', 'css' => 'old{}', 'is_premium' => true, 'copy_count' => 42,
    ]);
    $dir = makeDataDir(['modal.json' => [pattern('シンプルモーダル（Simple Modal）', '<p>new</p>', 'new{}')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])->assertExitCode(0);

    $row = UiPatternSnippet::first();
    expect($row->html)->toBe('<p>new</p>')
        ->and($row->css)->toBe('new{}')
        ->and($row->is_premium)->toBeTrue()
        ->and($row->copy_count)->toBe(42);
});

test('JSON にない既存行（管理画面から登録したもの）には触れない', function () {
    UiPatternSnippet::create(['category' => 'modal', 'title' => '手入力のモーダル', 'html' => '<p>a</p>', 'css' => 'a{}']);
    $dir = makeDataDir(['modal.json' => [pattern('シンプルモーダル（Simple Modal）')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])->assertExitCode(0);

    expect(UiPatternSnippet::where('title', '手入力のモーダル')->exists())->toBeTrue()
        ->and(UiPatternSnippet::count())->toBe(2);
});

test('カテゴリを指定すると、そのカテゴリのファイルだけ取り込む', function () {
    $dir = makeDataDir([
        'modal.json' => [pattern('モーダル（Modal）')],
        'toast.json' => [pattern('トースト（Toast）')],
    ]);

    $this->artisan('ui-patterns:import', ['category' => 'toast', '--path' => $dir])->assertExitCode(0);

    expect(UiPatternSnippet::pluck('category')->all())->toBe(['toast']);
});

test('壊れた JSON・不明なカテゴリ・必須項目の欠落は、何も書き込まずに失敗する', function (array $files, string $message) {
    $dir = makeDataDir($files + ['modal.json' => [pattern('正常なモーダル（Valid Modal）')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    expect(UiPatternSnippet::count())->toBe(0);
})->with([
    '壊れた JSON' => [['toast.json' => '{broken'], 'JSON'],
    '不明なカテゴリ' => [['nothing.json' => [pattern('x（X）')]], 'カテゴリ'],
    'html がない' => [['toast.json' => [['title' => 'a（A）', 'css' => 'a{}']]], 'html'],
]);
```

- [ ] **Step 2: 失敗を確認する**

Run: `php artisan test --filter=ImportUiPatternsCommandTest`
Expected: FAIL（コマンドがない）

- [ ] **Step 3: コマンドを実装する**

`app/Console/Commands/ImportUiPatternsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\UiPatternSnippet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use JsonException;

class ImportUiPatternsCommand extends Command
{
    protected $signature = 'ui-patterns:import
        {category? : 取り込むカテゴリ（省略するとすべて）}
        {--path= : データの置き場所（省略すると database/data/ui-patterns）}';

    protected $description = 'database/data/ui-patterns の JSON から UI パターンを取り込む（同じカテゴリ・タイトルは html/css だけ更新）';

    public function handle(): int
    {
        $dir = (string) ($this->option('path') ?: database_path('data/ui-patterns'));
        $only = $this->argument('category');

        if (! is_dir($dir)) {
            $this->error("データの置き場所がありません: {$dir}");

            return self::FAILURE;
        }

        $loaded = [];
        $errors = [];

        foreach (File::glob($dir.'/*.json') ?: [] as $file) {
            $category = basename($file, '.json');

            if ($only !== null && $only !== $category) {
                continue;
            }

            if (! in_array($category, UiPatternSnippet::CATEGORIES, true)) {
                $errors[] = "{$category}.json: カテゴリ「{$category}」は許可されていません";

                continue;
            }

            try {
                $items = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $errors[] = "{$category}.json: JSON が壊れています（{$e->getMessage()}）";

                continue;
            }

            foreach ($this->validate($category, $items) as $error) {
                $errors[] = $error;
            }

            $loaded[$category] = $items;
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            $this->error('エラーがあるため、何も取り込みませんでした。');

            return self::FAILURE;
        }

        $added = 0;
        $updated = 0;

        DB::transaction(function () use ($loaded, &$added, &$updated): void {
            foreach ($loaded as $category => $items) {
                foreach ($items as $item) {
                    $row = UiPatternSnippet::where('category', $category)->where('title', $item['title'])->first();

                    if ($row) {
                        $row->update(['html' => $item['html'], 'css' => $item['css']]);
                        $updated++;
                    } else {
                        UiPatternSnippet::create([
                            'category' => $category,
                            'title' => $item['title'],
                            'html' => $item['html'],
                            'css' => $item['css'],
                        ]);
                        $added++;
                    }
                }
            }
        });

        $this->info("追加 {$added} 件・更新 {$updated} 件");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function validate(string $category, mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            return ["{$category}.json: 配列ではありません"];
        }

        $errors = [];

        foreach ($items as $index => $item) {
            foreach (['title', 'html', 'css'] as $key) {
                if (! is_array($item) || ! isset($item[$key]) || ! is_string($item[$key]) || trim($item[$key]) === '') {
                    $errors[] = "{$category}.json: {$index} 番目の {$key} がありません";
                }
            }
        }

        return $errors;
    }
}
