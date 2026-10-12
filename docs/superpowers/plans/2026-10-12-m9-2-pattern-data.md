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

- [ ] **Step 4: コマンドのテストが通ることを確認する**

Run: `php artisan test --filter=ImportUiPatternsCommandTest`
Expected: PASS

- [ ] **Step 5: データの検査テストを書く**

`tests/Feature/UiPatternDataTest.php`（`database/data/ui-patterns/*.json` のすべてを検査。Task 2 以降でファイルが増えるたびに、検査の対象も増える）:

```php
<?php

use App\Models\UiPatternSnippet;
use Illuminate\Support\Facades\File;

/** カテゴリごとの最低件数（設計書 §4.4）。ファイルがあるカテゴリだけ検査する。 */
const UI_PATTERN_MIN_COUNTS = [
    'modal' => 10, 'toast' => 10, 'loading' => 10, 'skeleton' => 8, 'header' => 8, 'footer' => 8,
    'form-input' => 10, 'form-textarea' => 6, 'form-check' => 10, 'form-select' => 8, 'form-group' => 8,
    'pagination' => 7,
];

function uiPatternFiles(): array
{
    return File::glob(database_path('data/ui-patterns').'/*.json') ?: [];
}

test('データのファイル名はカテゴリの許可リストにある', function () {
    foreach (uiPatternFiles() as $file) {
        expect(UiPatternSnippet::CATEGORIES)->toContain(basename($file, '.json'));
    }
});

test('全パターンが必須項目・タイトル形式・決まりを満たす', function () {
    foreach (uiPatternFiles() as $file) {
        $category = basename($file, '.json');
        $items = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
        $titles = [];

        foreach ($items as $i => $item) {
            $where = "{$category}.json #{$i} ".($item['title'] ?? '');

            foreach (['title', 'html', 'css'] as $key) {
                expect($item[$key] ?? null, "$where: $key")->toBeString()->not->toBeEmpty();
            }

            expect(mb_strlen($item['title']), "$where: タイトルの長さ")->toBeLessThanOrEqual(255);
            expect($item['title'], "$where: 「日本語名（English Name）」の形式")->toMatch('/^.+（[A-Za-z0-9][A-Za-z0-9 \-\/&\.\+\']*）$/u');
            expect($titles, "$where: タイトルの重複")->not->toContain($item['title']);
            $titles[] = $item['title'];

            $code = $item['html']."\n".$item['css'];
            expect($code, "$where: script")->not->toMatch('/<\s*script/i');
            expect($code, "$where: position: fixed")->not->toMatch('/position\s*:\s*fixed/i');
            expect($code, "$where: vh/vw")->not->toMatch('/\d\s*(vh|vw|dvh|svh)\b/i');
            expect($code, "$where: @import")->not->toMatch('/@import/i');

            preg_match_all('#https?://[^\s"\'()<>]+#i', $code, $urls);
            foreach ($urls[0] as $url) {
                expect($url, "$where: 外部 URL")->toStartWith('https://placehold.co/');
            }
        }
    }
});

test('各カテゴリが最低件数を満たす', function () {
    foreach (uiPatternFiles() as $file) {
        $category = basename($file, '.json');
        $items = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);

        expect(count($items), "{$category}.json の件数")->toBeGreaterThanOrEqual(UI_PATTERN_MIN_COUNTS[$category] ?? 1);
    }
});
```

- [ ] **Step 6: Pint・PHPStan・全テストを通してコミットする**

Run: `composer test`
Expected: すべて通る（`UiPatternDataTest` は、ファイルがまだ無いので検査対象なしで通る）

```bash
git add app/Console database tests
git commit -m "feat(m9-2): UIパターンの取り込みコマンドと、データの検査テスト"
```

---

### データ作成タスクの共通手順（Task 2〜12）

各タスクで、設計書 §4.4 の種類を、1 つのカテゴリ分、`database/data/ui-patterns/<category>.json` に書く。

- [ ] **A. JSON を書く**: 設計書 §4.3 の作り方の決まりと、§4.4 の「種類」の順に、`{"title","html","css"}` を書く。タイトルは「日本語名（English Name）」。件数は §4.4 の件数以上。
- [ ] **B. 検査**: `php artisan test --filter=UiPatternDataTest` が通ること（外部 URL・script・fixed・vh/vw・件数・タイトル形式）。
- [ ] **C. 取り込み**: ローカルの一時 SQLite（`.env.m92`、MySQL が止まっているため。確認後に削除）で `php artisan ui-patterns:import <category>` を実行し、「追加 N 件」を確認。もう一度実行して「追加 0 件・更新 N 件」になること。
- [ ] **D. ブラウザ確認**: `/ui-pattern-<category>` を 1280px で開き、全件を撮る。切れ・崩れ・文字の読みにくさ・見切れたアニメーション（動かない・位置ずれ）がないことを確認し、あれば JSON を直して再取り込み。
- [ ] **E. コミット**: `git add database/data/ui-patterns/<category>.json && git commit -m "feat(m9-2): <カテゴリ名>のパターンを N 件追加"`

### Task 2: modal（10 件）

設計書 §4.4 の modal の種類（シンプル／確認／削除の警告／成功／入力フォーム付き／画像付き／ヘッダー色付き／サイドパネル風／全面／コンパクト）。ダイアログの箱を静的に置く（背景の暗幕は、箱を包む `position: relative` の枠と半透明の背景で表現してよい）。共通手順 A〜E。

### Task 3: toast（10 件）

toast の種類（成功／エラー／警告／情報／アイコン付き／閉じるボタン付き／進行バー付き／アクション付き／ダーク／左ボーダー強調）。通知の箱を静的に置く。進行バーは CSS アニメーション。共通手順 A〜E。

### Task 4: loading（10 件）

loading の種類（スピナー細・太／ドット 3 つ／バー／パルス／リング／波／回転四角／テキスト点滅／進捗円／グラデーション回転）。`@keyframes` は、そのパターンの CSS に含める。共通手順 A〜E。

### Task 5: skeleton（8 件）

skeleton の種類（テキスト行／アバター付き／カード／リスト／記事／テーブル行／プロフィール／画像グリッド）。シマー（光が流れる）は CSS アニメーション。共通手順 A〜E。

### Task 6: header（8 件）

header の種類（シンプル／中央ロゴ／ボタン付き／検索付き／ダーク／ハンバーガー／2 段／半透明）。このページは 1 列（横幅いっぱい）。ハンバーガーは JS なしの見た目だけ（チェックボックスのハック `:checked` は使ってよい）。共通手順 A〜E。

### Task 7: footer（8 件）

footer の種類（シンプル／多列リンク／中央寄せ／SNS 付き／ニュースレター付き／ダーク／ミニマル／CTA 付き）。1 列表示。SNS アイコンは絵文字か CSS の図形。共通手順 A〜E。

### Task 8: form-input（10 件）

form-input の種類（ボーダー／塗り／下線／アイコン付き／角丸／フローティングラベル／エラー／成功／無効／サイズ違い）。共通手順 A〜E。

### Task 9: form-textarea（6 件）

form-textarea の種類（ボーダー／塗り／文字数カウンタ付き／エラー／無効／拡大可能）。カウンタは見た目のみ（JS なし）。共通手順 A〜E。

### Task 10: form-check（10 件）

form-check の種類（チェック四角・丸・トグル／ラジオ標準・カード型・ボタン型／チェックリスト／無効／カラー選択／評価）。`:checked` を使って CSS だけで動かす。共通手順 A〜E。

### Task 11: form-select（8 件）

form-select の種類（ボーダー／塗り／下線／アイコン付き／グループ化（`<optgroup>`）／エラー／無効／サイズ違い）。共通手順 A〜E。

### Task 12: form-group（8 件）と pagination（+7 件）

form-group の種類（ラベル付き／ヘルプテキスト付き／横並び／必須マーク付き／エラー付き／2 列／ログイン／問い合わせ）と、pagination の追加（前へ/次へ／ドット／ページ数省略／もっと見る／ページ入力／簡易 ○/○／カード型）。pagination.json は、既存の本番 3 件のタイトルと重ならないよう、新しいタイトルだけを書く。共通手順 A〜E（2 ファイル分）。

### Task 13: 記録

- [ ] `SPEC.md` の UI パターン集の節に、取り込みコマンドとデータの置き場所、決まりを追記する（範囲を絞った `Edit` で。他の節を消さない）。
- [ ] `CLAUDE.md`（リポジトリ）の「本番反映の手順」に、反映後に `ui-patterns:import` を実行する手順を追記する。
- [ ] 会社側のロードマップに、M9-2 の結果メモ（件数・方式・本番反映の手順）を追記する。
- [ ] `composer test`・`npx vitest run`・`npm run lint`・`npm run types:check` がすべて通ること。コミット。

## 計画の自己確認

- 設計書の対応：§4.1 データの入れ方＝Task 1／§4.2 品質の決まり＝Task 1（`UiPatternDataTest`）／§4.3 作り方＝共通手順 A／§4.4 件数と種類＝Task 2〜12／§5 テスト・ブラウザ確認＝Task 1 と共通手順 D／§7 本番反映の手順＝Task 13。
- 型の一貫：データ形式 `{"title","html","css"}` を、コマンド・検査テスト・各データタスクで共通に使う。
