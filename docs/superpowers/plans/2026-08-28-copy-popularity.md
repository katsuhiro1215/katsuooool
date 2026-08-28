# カラー例・UIパターン集の「コピー人気度」計測機能 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** カラー3ツール(パレット/グラデーション/組み合わせ)にAdmin登録の「例」ギャラリーを追加し、既存のUIパターン集と共通の「コピー回数」計測の仕組みで人気順に並べ替えられるようにする。

**Architecture:** 新規`color_examples`テーブル(type列でpalette/gradient/schemeを区別する単一テーブル)と、匿名の重複コピー防止用`copy_events`テーブルを追加する。共通の`CopyPopularityTracker`サービスが両方の対象(`ColorExample`・`UiPatternSnippet`)からコピー計測を受け付け、日次ソルト付きIPハッシュで同日・同IP・同対象の重複カウントを防ぐ。カラー3コントローラーは共通トレイトでコピーAPIを提供し、UIパターン集は既存の`UiPatternSnippet.copy_count`列を増分するだけの単一コントローラーを新設する。

**Tech Stack:** Laravel 13 / Eloquent / Inertia.js v2 / Vue 3 + TypeScript / Pest / Vitest

**Spec:** `docs/superpowers/specs/2026-08-28-copy-popularity-design.md`

## Global Constraints

- ログイン不要。認証チェックは一切行わない。
- 重複防止は`tool_events`と同じ手法(`hash('sha256', $ip . now()->toDateString() . config('app.key'))`)を用いる。
- `copy_count`はDBのユニーク制約付き`copy_events`テーブルへの`createOrFirst`(Laravel 10+、ユニーク制約違反を内部で捕捉して再取得する。素の`firstOrCreate`は同時リクエストで制約違反の例外を投げてしまうため使わない)が新規作成に成功した場合のみ増分する。
- Admin側からは`copy_count`を直接編集できないようにする(バリデーションルールに含めない)。
- 新規エンドポイントはすべて`throttle:30,1`ミドルウェアを付与する。
- `color_examples`の`type`は`palette`・`gradient`・`scheme`の3値のみ。
- 既存の`ColorPalette.vue`・`ColorGradient.vue`・`ColorScheme.vue`の生成ツール自体の挙動(ランダム生成・ロック・track呼び出し)は変更しない。追加のギャラリーは既存UIの下に追記する。

---

## Task 1: `ColorExample`モデル・`CopyEvent`モデル・`CopyPopularityTracker`サービス

**Files:**
- Create: `database/migrations/2026_08_28_000001_create_color_examples_table.php`
- Create: `database/migrations/2026_08_28_000002_create_copy_events_table.php`
- Create: `app/Models/ColorExample.php`
- Create: `app/Models/CopyEvent.php`
- Create: `app/Services/CopyPopularityTracker.php`
- Test: `tests/Feature/Services/CopyPopularityTrackerTest.php`
- Test: `tests/Feature/Models/ColorExampleTest.php`

**Interfaces:**
- Produces: `ColorExample::TYPES` (`array{'palette','gradient','scheme'}`)、`ColorExample::scopeOfType(Builder $query, string $type): Builder`(`type`一致 かつ `copy_count`降順・`id`昇順)、`ColorExample::$colors`(配列キャスト)、`ColorExample::$copy_count`(int)。
- Produces: `CopyPopularityTracker::record(Model $copyable, ?string $ip): void` — Task 3・4で`ColorExample`・`UiPatternSnippet`の両方から呼び出される。

- [ ] **Step 1: `color_examples`マイグレーションを作成**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_examples', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['palette', 'gradient', 'scheme']);
            $table->string('title');
            $table->json('colors');
            $table->unsignedSmallInteger('angle')->nullable();
            $table->unsignedInteger('copy_count')->default(0);
            $table->timestamps();

            $table->index(['type', 'copy_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_examples');
    }
};
```

- [ ] **Step 2: `copy_events`マイグレーションを作成**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copy_events', function (Blueprint $table) {
            $table->id();
            $table->morphs('copyable');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['copyable_type', 'copyable_id', 'ip_hash'], 'copy_events_copyable_ip_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_events');
    }
};
```

- [ ] **Step 3: マイグレーションを実行**

Run: `php artisan migrate`
Expected: 両テーブルが作成される(`php artisan migrate:status`で確認)。

- [ ] **Step 4: `ColorExample`モデルの失敗するテストを書く**

```php
<?php

use App\Models\ColorExample;

test('ofType filters by type and orders by copy_count desc then id asc', function () {
    $lowCount = ColorExample::create([
        'type' => 'palette',
        'title' => '低人気パレット',
        'colors' => ['#111111', '#222222'],
        'copy_count' => 1,
    ]);
    $highCount = ColorExample::create([
        'type' => 'palette',
        'title' => '高人気パレット',
        'colors' => ['#333333', '#444444'],
        'copy_count' => 5,
    ]);
    ColorExample::create([
        'type' => 'gradient',
        'title' => '別typeのグラデーション',
        'colors' => ['#000000', '#ffffff'],
        'angle' => 90,
        'copy_count' => 100,
    ]);

    $results = ColorExample::ofType('palette')->get();

    expect($results)->toHaveCount(2);
    expect($results->first()->id)->toBe($highCount->id);
    expect($results->last()->id)->toBe($lowCount->id);
});

test('colors is cast to an array', function () {
    $example = ColorExample::create([
        'type' => 'scheme',
        'title' => '補色',
        'colors' => ['#3366ff', '#ff9933'],
    ]);

    $fresh = ColorExample::find($example->id);

    expect($fresh->colors)->toBe(['#3366ff', '#ff9933']);
});

test('copy_count defaults to zero', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'デフォルト値',
        'colors' => ['#111111', '#222222'],
    ]);

    expect($example->copy_count)->toBe(0);
});
```

- [ ] **Step 5: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Models/ColorExampleTest.php`
Expected: FAIL(`ColorExample`クラスが存在しない)

- [ ] **Step 6: `ColorExample`モデルを実装**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $title
 * @property array<int, string> $colors
 * @property int|null $angle
 * @property int $copy_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['type', 'title', 'colors', 'angle', 'copy_count'])]
class ColorExample extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'copy_count' => 0,
    ];

    /**
     * @var array<int, string>
     */
    public const TYPES = ['palette', 'gradient', 'scheme'];

    /**
     * @param  Builder<ColorExample>  $query
     * @return Builder<ColorExample>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderByDesc('copy_count')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'colors' => 'array',
        ];
    }
}
```

- [ ] **Step 7: モデルテストを再実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Models/ColorExampleTest.php`
Expected: PASS

- [ ] **Step 8: `CopyEvent`モデルを実装(テスト不要、`CopyPopularityTracker`のテストで間接的に検証)**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $copyable_type
 * @property int $copyable_id
 * @property string|null $ip_hash
 */
#[Fillable(['copyable_type', 'copyable_id', 'ip_hash'])]
class CopyEvent extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function copyable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 9: `CopyPopularityTracker`の失敗するテストを書く**

```php
<?php

use App\Models\ColorExample;
use App\Services\CopyPopularityTracker;
use Illuminate\Support\Carbon;

test('recording a copy increments copy_count once', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);

    (new CopyPopularityTracker)->record($example, '203.0.113.10');

    expect($example->fresh()->copy_count)->toBe(1);
});

test('the same ip recording twice on the same day only counts once', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);
    $tracker = new CopyPopularityTracker;

    $tracker->record($example, '203.0.113.10');
    $tracker->record($example, '203.0.113.10');

    expect($example->fresh()->copy_count)->toBe(1);
});

test('different ips each count separately', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);
    $tracker = new CopyPopularityTracker;

    $tracker->record($example, '203.0.113.10');
    $tracker->record($example, '203.0.113.20');

    expect($example->fresh()->copy_count)->toBe(2);
});

test('the same ip on a different day counts again', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);
    $tracker = new CopyPopularityTracker;

    Carbon::setTestNow('2026-08-28 10:00:00');
    $tracker->record($example, '203.0.113.10');

    Carbon::setTestNow('2026-08-29 10:00:00');
    $tracker->record($example, '203.0.113.10');

    Carbon::setTestNow();

    expect($example->fresh()->copy_count)->toBe(2);
});

test('a null ip still increments (no dedup guarantee for unresolvable ips)', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);

    (new CopyPopularityTracker)->record($example, null);

    expect($example->fresh()->copy_count)->toBe(1);
});
```

- [ ] **Step 10: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Services/CopyPopularityTrackerTest.php`
Expected: FAIL(`CopyPopularityTracker`クラスが存在しない)

- [ ] **Step 11: `CopyPopularityTracker`を実装**

```php
<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\CopyEvent;

class CopyPopularityTracker
{
    public function record(Model $copyable, ?string $ip): void
    {
        $ipHash = $this->hashIp($ip);

        $event = CopyEvent::query()->createOrFirst([
            'copyable_type' => $copyable::class,
            'copyable_id' => $copyable->getKey(),
            'ip_hash' => $ipHash,
        ]);

        if ($event->wasRecentlyCreated) {
            $copyable->increment('copy_count');
        }
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.now()->toDateString().config('app.key'));
    }
}
```

- [ ] **Step 12: テストを再実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Services/CopyPopularityTrackerTest.php`
Expected: PASS

- [ ] **Step 13: コミット**

```bash
git add database/migrations/2026_08_28_000001_create_color_examples_table.php database/migrations/2026_08_28_000002_create_copy_events_table.php app/Models/ColorExample.php app/Models/CopyEvent.php app/Services/CopyPopularityTracker.php tests/Feature/Services/CopyPopularityTrackerTest.php tests/Feature/Models/ColorExampleTest.php
git commit -m "feat: add ColorExample/CopyEvent models and CopyPopularityTracker service"
```

---

## Task 2: `ui_pattern_snippets`に`copy_count`を追加し人気順に並び替え

**Files:**
- Create: `database/migrations/2026_08_28_000003_add_copy_count_to_ui_pattern_snippets_table.php`
- Modify: `app/Models/UiPatternSnippet.php`
- Test: `tests/Feature/Models/UiPatternSnippetTest.php`(既存ファイルに追記)

**Interfaces:**
- Consumes: なし
- Produces: `UiPatternSnippet::$copy_count`(int)、`UiPatternSnippet::scopeForCategory()`が`copy_count`降順・`id`昇順で返す(Task 4で利用)

- [ ] **Step 1: マイグレーションを作成**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ui_pattern_snippets', function (Blueprint $table) {
            $table->unsignedInteger('copy_count')->default(0)->after('is_premium');
        });
    }

    public function down(): void
    {
        Schema::table('ui_pattern_snippets', function (Blueprint $table) {
            $table->dropColumn('copy_count');
        });
    }
};
```

- [ ] **Step 2: マイグレーションを実行**

Run: `php artisan migrate`
Expected: `ui_pattern_snippets`に`copy_count`列が追加される

- [ ] **Step 3: 失敗するテストを既存の`tests/Feature/Models/UiPatternSnippetTest.php`に追記**

既存ファイルの末尾に追記する:

```php
test('forCategory orders by copy_count desc then id asc', function () {
    $lowCount = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '低人気ボタン',
        'html' => '<a href="#">low</a>',
        'css' => 'a {}',
        'copy_count' => 1,
    ]);
    $highCount = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '高人気ボタン',
        'html' => '<a href="#">high</a>',
        'css' => 'a {}',
        'copy_count' => 5,
    ]);

    $results = UiPatternSnippet::forCategory('button')->get();

    expect($results->first()->id)->toBe($highCount->id);
    expect($results->last()->id)->toBe($lowCount->id);
});

test('copy_count defaults to zero', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'デフォルト値',
        'html' => '<a href="#">x</a>',
        'css' => 'a {}',
    ]);

    expect($snippet->copy_count)->toBe(0);
});
```

- [ ] **Step 4: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Models/UiPatternSnippetTest.php`
Expected: FAIL(`copy_count`カラム・ソート順が未実装)

- [ ] **Step 5: `UiPatternSnippet`モデルを更新**

`#[Fillable([...])]`に`'copy_count'`を追加し、`$attributes`にデフォルト値を追加、`scopeForCategory()`の並び順を変更する:

```php
#[Fillable(['category', 'title', 'html', 'css', 'is_premium', 'copy_count'])]
class UiPatternSnippet extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_premium' => false,
        'copy_count' => 0,
    ];

    // ... CATEGORIES はそのまま ...

    /**
     * @param  Builder<UiPatternSnippet>  $query
     * @return Builder<UiPatternSnippet>
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category)->orderByDesc('copy_count')->orderBy('id');
    }
```

- [ ] **Step 6: テストを再実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Models/UiPatternSnippetTest.php`
Expected: PASS

- [ ] **Step 7: 既存の`UiPatternButtonControllerTest.php`など、`id`昇順を前提にしたテストが壊れていないか確認**

Run: `./vendor/bin/pest tests/Feature/Tools/`
Expected: 全件PASS(既存テストは`copy_count`が全て0の状態で作成しているため、`id`昇順とタイの状態で結果は変わらないはず。壊れていた場合は該当テストのassert順序を見直す)

- [ ] **Step 8: コミット**

```bash
git add database/migrations/2026_08_28_000003_add_copy_count_to_ui_pattern_snippets_table.php app/Models/UiPatternSnippet.php tests/Feature/Models/UiPatternSnippetTest.php
git commit -m "feat: add copy_count to ui_pattern_snippets and sort by popularity"
```

---

## Task 3: カラー3ツールの「例」表示・コピーAPI(バックエンド)

**Files:**
- Create: `app/Http/Controllers/Tools/Concerns/ColorExampleCopyable.php`
- Modify: `app/Http/Controllers/Tools/ColorPaletteController.php`
- Modify: `app/Http/Controllers/Tools/ColorGradientController.php`
- Modify: `app/Http/Controllers/Tools/ColorSchemeController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Tools/ColorPaletteControllerTest.php`(既存ファイルに追記)
- Test: `tests/Feature/Tools/ColorGradientControllerTest.php`(既存ファイルに追記)
- Test: `tests/Feature/Tools/ColorSchemeControllerTest.php`(既存ファイルに追記)

**Interfaces:**
- Consumes: `ColorExample::ofType()`(Task 1)、`CopyPopularityTracker::record()`(Task 1)
- Produces: 各ページの`show`レスポンスに`examples`prop(配列、各要素`{id, title, colors, angle, copy_count}`)。`POST /color-palette/examples/{colorExample}/copy`などのJSONレスポンス`{status: 'ok'}`(Task 5のフロントエンドが呼び出す)

- [ ] **Step 1: 共通トレイトを作成**

```php
<?php

namespace App\Http\Controllers\Tools\Concerns;

use App\Models\ColorExample;
use App\Services\CopyPopularityTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ColorExampleCopyable
{
    abstract protected function exampleType(): string;

    public function copyExample(ColorExample $colorExample, Request $request, CopyPopularityTracker $tracker): JsonResponse
    {
        abort_unless($colorExample->type === $this->exampleType(), 404);

        $tracker->record($colorExample, $request->ip());

        return response()->json(['status' => 'ok']);
    }
}
```

- [ ] **Step 2: `ColorPaletteControllerTest.php`に失敗するテストを追記**

```php
use App\Models\ColorExample;

test('the show page includes palette-type examples ordered by copy_count', function () {
    $lowCount = ColorExample::create([
        'type' => 'palette',
        'title' => '低人気パレット',
        'colors' => ['#111111', '#222222'],
        'copy_count' => 1,
    ]);
    $highCount = ColorExample::create([
        'type' => 'palette',
        'title' => '高人気パレット',
        'colors' => ['#333333', '#444444'],
        'copy_count' => 5,
    ]);
    ColorExample::create([
        'type' => 'gradient',
        'title' => '別typeのグラデーション',
        'colors' => ['#000000', '#ffffff'],
        'angle' => 90,
    ]);

    $response = $this->get(route('color-palette.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ColorPalette')
        ->has('examples', 2)
        ->where('examples.0.id', $highCount->id)
        ->where('examples.1.id', $lowCount->id)
    );
});

test('copying a palette example increments its copy_count', function () {
    $example = ColorExample::create([
        'type' => 'palette',
        'title' => 'テストパレット',
        'colors' => ['#111111', '#222222'],
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-palette.examples.copy', $example));

    $response->assertSuccessful();
    expect($example->fresh()->copy_count)->toBe(1);
});

test('copying a non-palette example via the palette endpoint returns 404', function () {
    $gradientExample = ColorExample::create([
        'type' => 'gradient',
        'title' => 'グラデーション例',
        'colors' => ['#000000', '#ffffff'],
        'angle' => 90,
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-palette.examples.copy', $gradientExample));

    $response->assertStatus(404);
});
```

- [ ] **Step 3: `ColorGradientControllerTest.php`に失敗するテストを追記(パレットと対称)**

```php
use App\Models\ColorExample;

test('the show page includes gradient-type examples ordered by copy_count', function () {
    $lowCount = ColorExample::create([
        'type' => 'gradient',
        'title' => '低人気グラデーション',
        'colors' => ['#111111', '#222222'],
        'angle' => 45,
        'copy_count' => 1,
    ]);
    $highCount = ColorExample::create([
        'type' => 'gradient',
        'title' => '高人気グラデーション',
        'colors' => ['#333333', '#444444'],
        'angle' => 90,
        'copy_count' => 5,
    ]);
    ColorExample::create([
        'type' => 'palette',
        'title' => '別typeのパレット',
        'colors' => ['#000000', '#ffffff'],
    ]);

    $response = $this->get(route('color-gradient.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ColorGradient')
        ->has('examples', 2)
        ->where('examples.0.id', $highCount->id)
        ->where('examples.1.id', $lowCount->id)
    );
});

test('copying a gradient example increments its copy_count', function () {
    $example = ColorExample::create([
        'type' => 'gradient',
        'title' => 'テストグラデーション',
        'colors' => ['#111111', '#222222'],
        'angle' => 90,
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-gradient.examples.copy', $example));

    $response->assertSuccessful();
    expect($example->fresh()->copy_count)->toBe(1);
});

test('copying a non-gradient example via the gradient endpoint returns 404', function () {
    $paletteExample = ColorExample::create([
        'type' => 'palette',
        'title' => 'パレット例',
        'colors' => ['#000000', '#ffffff'],
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-gradient.examples.copy', $paletteExample));

    $response->assertStatus(404);
});
```

- [ ] **Step 4: `ColorSchemeControllerTest.php`に失敗するテストを追記(同様のパターンで`type: 'scheme'`)**

```php
use App\Models\ColorExample;

test('the show page includes scheme-type examples ordered by copy_count', function () {
    $lowCount = ColorExample::create([
        'type' => 'scheme',
        'title' => '低人気の組み合わせ',
        'colors' => ['#111111', '#222222'],
        'copy_count' => 1,
    ]);
    $highCount = ColorExample::create([
        'type' => 'scheme',
        'title' => '高人気の組み合わせ',
        'colors' => ['#333333', '#444444'],
        'copy_count' => 5,
    ]);
    ColorExample::create([
        'type' => 'palette',
        'title' => '別typeのパレット',
        'colors' => ['#000000', '#ffffff'],
    ]);

    $response = $this->get(route('color-scheme.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ColorScheme')
        ->has('examples', 2)
        ->where('examples.0.id', $highCount->id)
        ->where('examples.1.id', $lowCount->id)
    );
});

test('copying a scheme example increments its copy_count', function () {
    $example = ColorExample::create([
        'type' => 'scheme',
        'title' => 'テスト組み合わせ',
        'colors' => ['#111111', '#222222'],
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-scheme.examples.copy', $example));

    $response->assertSuccessful();
    expect($example->fresh()->copy_count)->toBe(1);
});

test('copying a non-scheme example via the scheme endpoint returns 404', function () {
    $paletteExample = ColorExample::create([
        'type' => 'palette',
        'title' => 'パレット例',
        'colors' => ['#000000', '#ffffff'],
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('color-scheme.examples.copy', $paletteExample));

    $response->assertStatus(404);
});
```

- [ ] **Step 5: テストを実行して全て失敗することを確認**

Run: `./vendor/bin/pest tests/Feature/Tools/ColorPaletteControllerTest.php tests/Feature/Tools/ColorGradientControllerTest.php tests/Feature/Tools/ColorSchemeControllerTest.php`
Expected: 新規テストがFAIL(`examples`propも`copy`エンドポイントも未実装)

- [ ] **Step 6: 3つのコントローラーを実装**

`app/Http/Controllers/Tools/ColorPaletteController.php`:

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorExampleCopyable;
use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\ColorExample;
use Illuminate\Http\Request;

class ColorPaletteController extends TrackedToolController
{
    use ColorExampleCopyable;

    protected function slug(): string
    {
        return 'color-palette';
    }

    protected function page(): string
    {
        return 'ColorPalette';
    }

    protected function exampleType(): string
    {
        return 'palette';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return [
            'examples' => ColorExample::ofType('palette')->get(['id', 'title', 'colors', 'angle', 'copy_count']),
        ];
    }
}
```

`app/Http/Controllers/Tools/ColorGradientController.php`(同じ形で`exampleType()`は`'gradient'`、`ofType('gradient')`):

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorExampleCopyable;
use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\ColorExample;
use Illuminate\Http\Request;

class ColorGradientController extends TrackedToolController
{
    use ColorExampleCopyable;

    protected function slug(): string
    {
        return 'color-gradient';
    }

    protected function page(): string
    {
        return 'ColorGradient';
    }

    protected function exampleType(): string
    {
        return 'gradient';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return [
            'examples' => ColorExample::ofType('gradient')->get(['id', 'title', 'colors', 'angle', 'copy_count']),
        ];
    }
}
```

`app/Http/Controllers/Tools/ColorSchemeController.php`(同じ形で`exampleType()`は`'scheme'`、`ofType('scheme')`):

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\ColorExampleCopyable;
use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\ColorExample;
use Illuminate\Http\Request;

class ColorSchemeController extends TrackedToolController
{
    use ColorExampleCopyable;

    protected function slug(): string
    {
        return 'color-scheme';
    }

    protected function page(): string
    {
        return 'ColorScheme';
    }

    protected function exampleType(): string
    {
        return 'scheme';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return [
            'examples' => ColorExample::ofType('scheme')->get(['id', 'title', 'colors', 'angle', 'copy_count']),
        ];
    }
}
```

- [ ] **Step 7: `routes/web.php`にコピーエンドポイントを追加**

`Route::get('/color-palette', ...)`など既存の3つの`show`ルート定義のすぐ下に追加する(`use App\Http\Controllers\Tools\ColorPaletteController;`等は既にファイル冒頭でuse済みのため追加不要):

```php
Route::post('/color-palette/examples/{colorExample}/copy', [ColorPaletteController::class, 'copyExample'])
    ->middleware('throttle:30,1')
    ->name('color-palette.examples.copy');

Route::post('/color-gradient/examples/{colorExample}/copy', [ColorGradientController::class, 'copyExample'])
    ->middleware('throttle:30,1')
    ->name('color-gradient.examples.copy');

Route::post('/color-scheme/examples/{colorExample}/copy', [ColorSchemeController::class, 'copyExample'])
    ->middleware('throttle:30,1')
    ->name('color-scheme.examples.copy');
```

- [ ] **Step 8: テストを再実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Tools/ColorPaletteControllerTest.php tests/Feature/Tools/ColorGradientControllerTest.php tests/Feature/Tools/ColorSchemeControllerTest.php`
Expected: 全件PASS

- [ ] **Step 9: PHPStanを実行**

Run: `./vendor/bin/phpstan analyse --memory-limit=1G`
Expected: 0 errors

- [ ] **Step 10: コミット**

```bash
git add app/Http/Controllers/Tools/Concerns/ColorExampleCopyable.php app/Http/Controllers/Tools/ColorPaletteController.php app/Http/Controllers/Tools/ColorGradientController.php app/Http/Controllers/Tools/ColorSchemeController.php routes/web.php tests/Feature/Tools/ColorPaletteControllerTest.php tests/Feature/Tools/ColorGradientControllerTest.php tests/Feature/Tools/ColorSchemeControllerTest.php
git commit -m "feat: expose color example galleries and copy-tracking endpoints"
```

---

## Task 4: UIパターン集のコピー計測(バックエンド+`PatternCard.vue`+14ページ)

**Files:**
- Create: `app/Http/Controllers/Tools/UiPatternCopyController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/components/tools/PatternCard.vue`
- Modify: `resources/js/components/tools/PatternCard.test.ts`
- Modify: 14個の`resources/js/pages/UiPattern{Category}.vue`(button/card/pagination/modal/toast/loading/skeleton/form-input/form-textarea/form-check/form-select/form-group/header/footer)
- Test: `tests/Feature/Tools/UiPatternCopyControllerTest.php`

**Interfaces:**
- Consumes: `CopyPopularityTracker::record()`(Task 1)、`UiPatternSnippet::forCategory()`の並び順変更(Task 2、既に反映済み)
- Produces: `PatternCard.vue`が新規`id: number`propを必須で受け取るようになる(14ページすべての呼び出し元を更新する必要あり)

- [ ] **Step 1: `UiPatternCopyControllerTest.php`に失敗するテストを書く**

```php
<?php

use App\Models\UiPatternSnippet;

test('posting to the copy endpoint increments the snippet copy_count', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'テストボタン',
        'html' => '<a href="#">x</a>',
        'css' => 'a {}',
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));

    $response->assertSuccessful();
    expect($snippet->fresh()->copy_count)->toBe(1);
});

test('posting twice from the same ip on the same day only counts once', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'テストカード',
        'html' => '<div>x</div>',
        'css' => '.card {}',
    ]);

    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));

    expect($snippet->fresh()->copy_count)->toBe(1);
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Tools/UiPatternCopyControllerTest.php`
Expected: FAIL(ルート未定義)

- [ ] **Step 3: `UiPatternCopyController`を実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\UiPatternSnippet;
use App\Services\CopyPopularityTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UiPatternCopyController extends Controller
{
    public function store(UiPatternSnippet $uiPatternSnippet, Request $request, CopyPopularityTracker $tracker): JsonResponse
    {
        $tracker->record($uiPatternSnippet, $request->ip());

        return response()->json(['status' => 'ok']);
    }
}
```

- [ ] **Step 4: `routes/web.php`にルートを追加**

UIパターン集の14ルート定義ブロックの直後に1本だけ追加する(`use App\Http\Controllers\Tools\UiPatternCopyController;`をファイル冒頭のuse文に追加すること):

```php
Route::post('/ui-pattern-snippets/{uiPatternSnippet}/copy', [UiPatternCopyController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-snippets.copy');
```

- [ ] **Step 5: テストを再実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Tools/UiPatternCopyControllerTest.php`
Expected: PASS

- [ ] **Step 6: `PatternCard.test.ts`を更新**

現在のファイル冒頭は次の内容になっている:

```typescript
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import PatternCard from './PatternCard.vue';

const html = '<a href="#">Button</a>';
const css = 'a { color: red; }';
```

これを次のように置き換える(`axios`のimportと`vi.mock('axios')`、`beforeEach`セットアップを追加し、`vitest`からのimportに`beforeEach`と`vi`を加える):

```typescript
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, expect, test, vi } from 'vitest';

import PatternCard from './PatternCard.vue';

vi.mock('axios');

const html = '<a href="#">Button</a>';
const css = 'a { color: red; }';

beforeEach(() => {
    vi.stubGlobal('route', (_name: string, params: number) => {
        return `/ui-pattern-snippets/${params}/copy`;
    });
    Object.assign(navigator, {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
    vi.mocked(axios.post).mockResolvedValue({ data: { status: 'ok' } });
});
```

`navigator.clipboard`はjsdom環境に実装されていないため、モックしないと`copyCode()`内の`await navigator.clipboard.writeText(...)`が例外を投げ、その後続のコピー計測(`axios.post`)まで実行されずにテストが失敗する。

次に、ファイル内の既存4つの`mount(PatternCard, { props: { ... } })`呼び出し(タブ切り替え・srcdoc埋め込み・ロック表示2件のテスト)すべてに`id: 1`を追加する。例えば1つ目のテストは:

```typescript
test('switches the displayed code from HTML to CSS when the CSS tab is clicked', async () => {
    const wrapper = mount(PatternCard, {
        props: { id: 1, title: 'サンプルボタン', locked: false, html, css },
    });
```

のように、既存の`props: { title: ..., locked: ..., ... }`の先頭に`id: 1,`を追加する(ロック状態のテストにも同様に`id: 1`を追加する)。

最後に、ファイル末尾に次の2つのテストを追加する:

```typescript
test('clicking copy posts to the copy-tracking endpoint with the pattern id', async () => {
    const wrapper = mount(PatternCard, {
        props: { id: 42, title: 'サンプルボタン', locked: false, html, css },
    });

    const copyButton = wrapper
        .findAll('button')
        .find((button) => button.text() === 'コピー');
    await copyButton?.trigger('click');

    expect(axios.post).toHaveBeenCalledWith('/ui-pattern-snippets/42/copy');
});

test('a locked pattern never triggers a copy-tracking request (no copy button exists)', () => {
    mount(PatternCard, {
        props: {
            id: 99,
            title: 'Premiumボタン',
            locked: true,
            lockedSrcdoc: '<!doctype html><html><body>x</body></html>',
        },
    });

    expect(axios.post).not.toHaveBeenCalled();
});
```

- [ ] **Step 7: テストを実行して失敗を確認**

Run: `npx vitest run resources/js/components/tools/PatternCard.test.ts`
Expected: FAIL(`id`propが存在しない、コピー時にaxios呼び出しがない)

- [ ] **Step 8: `PatternCard.vue`を更新**

`id: number`propを追加し、`copyCode()`内でコピー成功後に計測APIを呼ぶ:

```vue
<script setup lang="ts">
import axios from 'axios';
import { computed, ref } from 'vue';

import { buildPatternDocument } from '@/lib/uiPatternSnippet';

const props = defineProps<{
    id: number;
    title: string;
    locked: boolean;
    html?: string;
    css?: string;
    lockedSrcdoc?: string;
}>();

const activeTab = ref<'html' | 'css'>('html');
const copied = ref(false);

const srcdoc = computed(() =>
    props.locked
        ? (props.lockedSrcdoc ?? '')
        : buildPatternDocument(props.html ?? '', props.css ?? ''),
);

const activeCode = computed(() =>
    activeTab.value === 'html' ? (props.html ?? '') : (props.css ?? ''),
);

async function copyCode() {
    try {
        await navigator.clipboard.writeText(activeCode.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);

        axios.post(route('ui-pattern-snippets.copy', props.id)).catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}
</script>
```

テンプレート部分は変更不要(既存のまま)。

- [ ] **Step 9: テストを再実行して成功を確認**

Run: `npx vitest run resources/js/components/tools/PatternCard.test.ts`
Expected: PASS(既存のタブ切り替え・ロック表示テストも含めて全件PASSすること)

- [ ] **Step 10: 14個の`UiPattern{Category}.vue`ページに`:id="pattern.id"`を追加**

対象ファイル(すべて`<PatternCard`呼び出し箇所に`:id="pattern.id"`を1行追加するだけの機械的な変更):

- `resources/js/pages/UiPatternButton.vue`
- `resources/js/pages/UiPatternCard.vue`
- `resources/js/pages/UiPatternPagination.vue`
- `resources/js/pages/UiPatternModal.vue`
- `resources/js/pages/UiPatternToast.vue`
- `resources/js/pages/UiPatternLoading.vue`
- `resources/js/pages/UiPatternSkeleton.vue`
- `resources/js/pages/UiPatternFormInput.vue`
- `resources/js/pages/UiPatternFormTextarea.vue`
- `resources/js/pages/UiPatternFormCheck.vue`
- `resources/js/pages/UiPatternFormSelect.vue`
- `resources/js/pages/UiPatternFormGroup.vue`
- `resources/js/pages/UiPatternHeader.vue`
- `resources/js/pages/UiPatternFooter.vue`

各ファイルで、次の形になっている`<PatternCard>`呼び出しに:

```vue
<PatternCard
    v-for="pattern in props.patterns"
    :key="pattern.id"
    :title="pattern.title"
    :locked="pattern.locked"
    :html="pattern.html"
    :css="pattern.css"
    :locked-srcdoc="pattern.srcdoc"
/>
```

`:id="pattern.id"`を追加する:

```vue
<PatternCard
    v-for="pattern in props.patterns"
    :key="pattern.id"
    :id="pattern.id"
    :title="pattern.title"
    :locked="pattern.locked"
    :html="pattern.html"
    :css="pattern.css"
    :locked-srcdoc="pattern.srcdoc"
/>
```

(ページによって属性の順序が多少異なる場合があるが、`:id="pattern.id"`をどこか1行追加すればよい。`pattern.id`という式は14ページすべてで既に`:key`に使われているため必ず存在する。)

- [ ] **Step 11: 型チェック・Lintを実行**

Run: `npm run types:check && npm run lint:check`
Expected: エラーなし(`PatternCard`の`id`が必須propになったことで、もし1ページでも追加を忘れていればvue-tscがエラーを出す)

- [ ] **Step 12: 既存の14個の`UiPattern{Category}ControllerTest.php`を実行し、Task 2の並び順変更が壊れていないか確認**

Run: `./vendor/bin/pest tests/Feature/Tools/`
Expected: 全件PASS

- [ ] **Step 13: コミット**

```bash
git add app/Http/Controllers/Tools/UiPatternCopyController.php routes/web.php resources/js/components/tools/PatternCard.vue resources/js/components/tools/PatternCard.test.ts resources/js/pages/UiPattern*.vue tests/Feature/Tools/UiPatternCopyControllerTest.php
git commit -m "feat: track copy-button clicks on UI pattern gallery cards"
```

---

## Task 5: カラー3ツールの「例」ギャラリー(フロントエンド)

**Files:**
- Create: `resources/js/components/tools/ColorExampleGallery.vue`
- Create: `resources/js/components/tools/ColorExampleGallery.test.ts`
- Modify: `resources/js/pages/ColorPalette.vue`
- Modify: `resources/js/pages/ColorGradient.vue`
- Modify: `resources/js/pages/ColorScheme.vue`

**Interfaces:**
- Consumes: Task 3で追加された`examples`props(`{id, title, colors, angle, copy_count}[]`)
- Produces: なし(末端のUIコンポーネント)

- [ ] **Step 1: `ColorExampleGallery.test.ts`に失敗するテストを書く**

```typescript
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import axios from 'axios';
import { beforeEach, expect, test, vi } from 'vitest';

import ColorExampleGallery from './ColorExampleGallery.vue';

vi.mock('axios');

beforeEach(() => {
    vi.stubGlobal('route', (name: string, params: number) => {
        return `/${name.replace('.examples.copy', '')}/examples/${params}/copy`;
    });
    Object.assign(navigator, {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
    vi.mocked(axios.post).mockResolvedValue({ data: { status: 'ok' } });
});

test('renders nothing when there are no examples', () => {
    const wrapper = mount(ColorExampleGallery, {
        props: { examples: [], type: 'palette' },
    });

    expect(wrapper.find('section').exists()).toBe(false);
});

test('renders a palette example with its swatches', () => {
    const wrapper = mount(ColorExampleGallery, {
        props: {
            examples: [
                { id: 1, title: '夏のパレット', colors: ['#3366ff', '#ff9933'], angle: null, copy_count: 3 },
            ],
            type: 'palette',
        },
    });

    expect(wrapper.text()).toContain('夏のパレット');
    expect(wrapper.findAll('[style*="background-color"]')).toHaveLength(2);
});

test('copying a palette example writes a comma-separated hex string and tracks the copy', async () => {
    const wrapper = mount(ColorExampleGallery, {
        props: {
            examples: [
                { id: 7, title: '夏のパレット', colors: ['#3366ff', '#ff9933'], angle: null, copy_count: 0 },
            ],
            type: 'palette',
        },
    });

    const copyButton = wrapper
        .findAll('button')
        .find((button) => button.text().includes('コピー'));
    await copyButton?.trigger('click');

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('#3366ff, #ff9933');
    expect(axios.post).toHaveBeenCalledWith('/color-palette/examples/7/copy');
});

test('copying a gradient example writes a CSS background declaration', async () => {
    const wrapper = mount(ColorExampleGallery, {
        props: {
            examples: [
                { id: 9, title: '夕焼け', colors: ['#3366ff', '#ff9933'], angle: 90, copy_count: 0 },
            ],
            type: 'gradient',
        },
    });

    const copyButton = wrapper
        .findAll('button')
        .find((button) => button.text().includes('コピー'));
    await copyButton?.trigger('click');

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        'background: linear-gradient(90deg, #3366ff, #ff9933);',
    );
    expect(axios.post).toHaveBeenCalledWith('/color-gradient/examples/9/copy');
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `npx vitest run resources/js/components/tools/ColorExampleGallery.test.ts`
Expected: FAIL(コンポーネントが存在しない)

- [ ] **Step 3: `ColorExampleGallery.vue`を実装**

```vue
<script setup lang="ts">
import axios from 'axios';
import { computed, ref } from 'vue';

interface ColorExampleData {
    id: number;
    title: string;
    colors: string[];
    angle: number | null;
    copy_count: number;
}

const props = defineProps<{
    examples: ColorExampleData[];
    type: 'palette' | 'gradient' | 'scheme';
}>();

const copiedId = ref<number | null>(null);

const routeNameByType: Record<typeof props.type, string> = {
    palette: 'color-palette.examples.copy',
    gradient: 'color-gradient.examples.copy',
    scheme: 'color-scheme.examples.copy',
};

function copyPayload(example: ColorExampleData): string {
    if (props.type === 'gradient') {
        return `background: linear-gradient(${example.angle}deg, ${example.colors[0]}, ${example.colors[1]});`;
    }

    return example.colors.join(', ');
}

const gradientPreview = computed(() => (example: ColorExampleData) =>
    `linear-gradient(${example.angle}deg, ${example.colors[0]}, ${example.colors[1]})`,
);

async function copy(example: ColorExampleData) {
    try {
        await navigator.clipboard.writeText(copyPayload(example));
        copiedId.value = example.id;
        setTimeout(() => {
            if (copiedId.value === example.id) {
                copiedId.value = null;
            }
        }, 1500);

        axios
            .post(route(routeNameByType[props.type], example.id))
            .catch(() => {
                // Analytics-only endpoint; ignore failures.
            });
    } catch {
        // Clipboard unavailable; the value is still visible as swatches.
    }
}
</script>

<template>
    <section v-if="examples.length > 0" class="mt-16">
        <h2 class="text-lg font-bold text-gray-900">人気の例</h2>
        <p class="mt-1 text-sm text-gray-600">
            気に入ったものをそのままコピーして使えます。
        </p>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div
                v-for="example in examples"
                :key="example.id"
                class="rounded-lg border border-gray-200 bg-white p-4"
            >
                <p class="mb-3 text-sm font-medium text-gray-900">
                    {{ example.title }}
                </p>

                <div
                    v-if="type === 'gradient'"
                    class="h-20 rounded-md border border-gray-200"
                    :style="{ backgroundImage: gradientPreview(example) }"
                />
                <div v-else class="flex gap-2">
                    <div
                        v-for="(hex, index) in example.colors"
                        :key="index"
                        class="h-12 w-12 rounded-md border border-gray-200"
                        :style="{ backgroundColor: hex }"
                    />
                </div>

                <button
                    type="button"
                    class="mt-3 rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:border-gray-400"
                    @click="copy(example)"
                >
                    {{ copiedId === example.id ? 'コピーしました' : 'コピー' }}
                </button>
            </div>
        </div>
    </section>
</template>
```

- [ ] **Step 4: テストを再実行して成功を確認**

Run: `npx vitest run resources/js/components/tools/ColorExampleGallery.test.ts`
Expected: PASS

- [ ] **Step 5: `ColorPalette.vue`にギャラリーを組み込む**

`<script setup>`のimportに追加:

```typescript
import ColorExampleGallery from '@/components/tools/ColorExampleGallery.vue';
```

`defineProps`を追加(このページには現状propsが無いため新規追加):

```typescript
interface ColorExampleData {
    id: number;
    title: string;
    colors: string[];
    angle: number | null;
    copy_count: number;
}

const props = defineProps<{ examples: ColorExampleData[] }>();
```

`</PageMain>`の直前、既存のswatchグリッドの直後に追加:

```vue
                <ColorExampleGallery :examples="props.examples" type="palette" />
```

- [ ] **Step 6: `ColorGradient.vue`にギャラリーを組み込む(同様の手順、`type="gradient"`)**

同じ形で`import`・`defineProps`・テンプレート末尾への追加を行う。

- [ ] **Step 7: `ColorScheme.vue`にギャラリーを組み込む(同様の手順、`type="scheme"`)**

同じ形で`import`・`defineProps`・テンプレート末尾への追加を行う。

- [ ] **Step 8: 型チェック・Lintを実行**

Run: `npm run types:check && npm run lint:check`
Expected: エラーなし

- [ ] **Step 9: コミット**

```bash
git add resources/js/components/tools/ColorExampleGallery.vue resources/js/components/tools/ColorExampleGallery.test.ts resources/js/pages/ColorPalette.vue resources/js/pages/ColorGradient.vue resources/js/pages/ColorScheme.vue
git commit -m "feat: add curated example galleries to color tools"
```

---

## Task 6: Admin CRUD(`color_examples`管理画面)

**Files:**
- Create: `app/Http/Controllers/Admin/ColorExampleController.php`
- Modify: `routes/admin.php`
- Create: `resources/js/components/admin/ColorExampleForm.vue`
- Create: `resources/js/pages/admin/color-examples/Index.vue`
- Create: `resources/js/pages/admin/color-examples/Create.vue`
- Create: `resources/js/pages/admin/color-examples/Edit.vue`
- Modify: `resources/js/layouts/AdminLayout.vue`
- Test: `tests/Feature/Admin/ColorExampleControllerTest.php`

**Interfaces:**
- Consumes: `ColorExample`モデル(Task 1)
- Produces: なし(末端のAdmin機能)

- [ ] **Step 1: `ColorExampleControllerTest.php`に失敗するテストを書く**

`actingAsAdmin()`は`tests/Pest.php`に定義済みのグローバルヘルパー(Adminを作成しログイン状態にする)なので、そのまま利用する。再定義しないこと。

```php
<?php

use App\Models\ColorExample;

test('the index page lists color examples', function () {
    actingAsAdmin();
    ColorExample::create(['type' => 'palette', 'title' => 'テスト例', 'colors' => ['#111111', '#222222']]);

    $response = $this->get(route('admin.color-examples.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/color-examples/Index')
        ->has('examples.data', 1)
    );
});

test('storing a valid palette example succeeds', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.color-examples.store'), [
        'type' => 'palette',
        'title' => '夏のパレット',
        'colors' => ['#3366ff', '#ff9933'],
    ]);

    $response->assertRedirect(route('admin.color-examples.index'));
    expect(ColorExample::where('title', '夏のパレット')->exists())->toBeTrue();
});

test('storing a gradient example without an angle fails validation', function () {
    actingAsAdmin();

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.color-examples.store'), [
            'type' => 'gradient',
            'title' => '夕焼け',
            'colors' => ['#3366ff', '#ff9933'],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['angle']);
});

test('storing an example with an invalid hex color fails validation', function () {
    actingAsAdmin();

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.color-examples.store'), [
            'type' => 'palette',
            'title' => '不正な色',
            'colors' => ['not-a-color', '#222222'],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['colors.0']);
});

test('copy_count cannot be set via the form', function () {
    actingAsAdmin();

    $this->post(route('admin.color-examples.store'), [
        'type' => 'palette',
        'title' => 'テスト',
        'colors' => ['#111111', '#222222'],
        'copy_count' => 999,
    ]);

    expect(ColorExample::where('title', 'テスト')->first()->copy_count)->toBe(0);
});

test('updating an example works', function () {
    actingAsAdmin();
    $example = ColorExample::create(['type' => 'palette', 'title' => '旧タイトル', 'colors' => ['#111111', '#222222']]);

    $response = $this->put(route('admin.color-examples.update', $example), [
        'type' => 'palette',
        'title' => '新タイトル',
        'colors' => ['#333333', '#444444'],
    ]);

    $response->assertRedirect(route('admin.color-examples.index'));
    expect($example->fresh()->title)->toBe('新タイトル');
});

test('destroying an example removes it', function () {
    actingAsAdmin();
    $example = ColorExample::create(['type' => 'palette', 'title' => '削除対象', 'colors' => ['#111111', '#222222']]);

    $response = $this->delete(route('admin.color-examples.destroy', $example));

    $response->assertRedirect(route('admin.color-examples.index'));
    expect(ColorExample::find($example->id))->toBeNull();
});

test('guests cannot access color example admin routes', function () {
    $response = $this->get(route('admin.color-examples.index'));

    $response->assertRedirect(route('admin.login'));
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/ColorExampleControllerTest.php`
Expected: FAIL(コントローラー・ルートが存在しない)

- [ ] **Step 3: `ColorExampleController`を実装**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ColorExample;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ColorExampleController extends Controller
{
    public function index(): Response
    {
        $examples = ColorExample::query()
            ->orderBy('type')
            ->orderBy('id')
            ->paginate(20);

        return Inertia::render('admin/color-examples/Index', [
            'examples' => $examples,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/color-examples/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        ColorExample::create($this->validated($request));

        return Redirect::route('admin.color-examples.index');
    }

    public function edit(ColorExample $colorExample): Response
    {
        return Inertia::render('admin/color-examples/Edit', [
            'example' => [
                'id' => $colorExample->id,
                'type' => $colorExample->type,
                'title' => $colorExample->title,
                'colors' => $colorExample->colors,
                'angle' => $colorExample->angle,
            ],
        ]);
    }

    public function update(Request $request, ColorExample $colorExample): RedirectResponse
    {
        $colorExample->update($this->validated($request));

        return Redirect::route('admin.color-examples.index');
    }

    public function destroy(ColorExample $colorExample): RedirectResponse
    {
        $colorExample->delete();

        return Redirect::route('admin.color-examples.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $type = $request->string('type')->toString();

        return $request->validate([
            'type' => ['required', 'string', Rule::in(ColorExample::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'colors' => [
                'required',
                'array',
                $type === 'gradient' ? 'size:2' : 'min:2',
                $type === 'palette' ? 'max:6' : ($type === 'scheme' ? 'max:4' : 'max:2'),
            ],
            'colors.*' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'angle' => [$type === 'gradient' ? 'required' : 'nullable', 'integer', 'min:0', 'max:360'],
        ]);
    }
}
```

- [ ] **Step 4: `routes/admin.php`にルートを追加**

`use App\Http\Controllers\Admin\ColorExampleController;`をファイル冒頭のuse文に追加し、`ui-pattern-snippets`のルート群の直後に追加する:

```php
Route::get('color-examples', [ColorExampleController::class, 'index'])->name('color-examples.index');
Route::get('color-examples/create', [ColorExampleController::class, 'create'])->name('color-examples.create');
Route::post('color-examples', [ColorExampleController::class, 'store'])->name('color-examples.store');
Route::get('color-examples/{colorExample}/edit', [ColorExampleController::class, 'edit'])->name('color-examples.edit');
Route::put('color-examples/{colorExample}', [ColorExampleController::class, 'update'])->name('color-examples.update');
Route::delete('color-examples/{colorExample}', [ColorExampleController::class, 'destroy'])->name('color-examples.destroy');
```

- [ ] **Step 5: テストを再実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/ColorExampleControllerTest.php`
Expected: PASS

- [ ] **Step 6: `ColorExampleForm.vue`を実装**

```vue
<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ColorExampleFormData {
    type: string;
    title: string;
    colors: string[];
    angle: number | null;
}

const props = defineProps<{
    form: InertiaForm<ColorExampleFormData>;
    submitLabel: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const colorsText = computed({
    get: () => props.form.colors.join(', '),
    set: (value: string) => {
        props.form.colors = value
            .split(',')
            .map((hex) => hex.trim())
            .filter((hex) => hex.length > 0);
    },
});
</script>

<template>
    <!-- eslint-disable vue/no-mutating-props -- InertiaForm is a shared reactive form object designed to be mutated in place via v-model from the parent's useForm() call. -->
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div>
            <label class="block text-sm font-medium text-gray-700" for="type"
                >種別</label
            >
            <select
                id="type"
                v-model="form.type"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                <option value="palette">パレット</option>
                <option value="gradient">グラデーション</option>
                <option value="scheme">色の組み合わせ</option>
            </select>
            <p v-if="form.errors.type" class="mt-1 text-xs text-red-600">
                {{ form.errors.type }}
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700" for="title"
                >タイトル</label
            >
            <input
                id="title"
                v-model="form.title"
                type="text"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">
                {{ form.errors.title }}
            </p>
        </div>

        <div>
            <label
                class="block text-sm font-medium text-gray-700"
                for="colors"
                >色(カンマ区切りのhex、例: #3366ff, #ff9933)</label
            >
            <input
                id="colors"
                v-model="colorsText"
                type="text"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <p
                v-if="form.errors['colors.0'] || form.errors.colors"
                class="mt-1 text-xs text-red-600"
            >
                {{ form.errors['colors.0'] ?? form.errors.colors }}
            </p>
        </div>

        <div v-if="form.type === 'gradient'">
            <label class="block text-sm font-medium text-gray-700" for="angle"
                >角度(0〜360)</label
            >
            <input
                id="angle"
                v-model.number="form.angle"
                type="number"
                min="0"
                max="360"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <p v-if="form.errors.angle" class="mt-1 text-xs text-red-600">
                {{ form.errors.angle }}
            </p>
        </div>

        <button
            type="submit"
            class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
            :disabled="form.processing"
        >
            {{ submitLabel }}
        </button>
    </form>
</template>
```

- [ ] **Step 7: `admin/color-examples/Create.vue`を実装**

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ColorExampleForm from '@/components/admin/ColorExampleForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const form = useForm({
    type: 'palette',
    title: '',
    colors: [] as string[],
    angle: null as number | null,
});

function submit() {
    form.post(route('admin.color-examples.store'));
}
</script>

<template>
    <Head title="カラー例を新規作成 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">カラー例を新規作成</h1>
        </template>

        <ColorExampleForm :form="form" submit-label="作成する" @submit="submit" />
    </AdminLayout>
</template>
```

- [ ] **Step 8: `admin/color-examples/Edit.vue`を実装**

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import ColorExampleForm from '@/components/admin/ColorExampleForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface ColorExampleDetail {
    id: number;
    type: string;
    title: string;
    colors: string[];
    angle: number | null;
}

const props = defineProps<{ example: ColorExampleDetail }>();

const form = useForm({
    type: props.example.type,
    title: props.example.title,
    colors: props.example.colors,
    angle: props.example.angle,
});

function submit() {
    form.put(route('admin.color-examples.update', props.example.id));
}

function destroy() {
    if (confirm('このカラー例を削除しますか?')) {
        router.delete(route('admin.color-examples.destroy', props.example.id));
    }
}
</script>

<template>
    <Head title="カラー例を編集 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">カラー例を編集</h1>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    @click="destroy"
                >
                    削除する
                </button>
            </div>
        </template>

        <ColorExampleForm :form="form" submit-label="更新する" @submit="submit" />
    </AdminLayout>
</template>
```

- [ ] **Step 9: `admin/color-examples/Index.vue`を実装**

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

const TYPE_LABELS: Record<string, string> = {
    palette: 'パレット',
    gradient: 'グラデーション',
    scheme: '色の組み合わせ',
};

interface ExampleRow {
    id: number;
    type: string;
    title: string;
    copy_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
}

defineProps<{ examples: Paginated<ExampleRow> }>();
</script>

<template>
    <Head title="カラー例管理 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">カラー例管理</h1>
                <Link
                    :href="route('admin.color-examples.create')"
                    class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                >
                    新規作成
                </Link>
            </div>
        </template>

        <div v-if="examples.data.length === 0" class="text-sm text-gray-500">
            カラー例がありません。
        </div>

        <div
            v-else
            class="overflow-hidden rounded-lg border border-gray-200 bg-white"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-gray-200 bg-gray-50 text-gray-500"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">タイトル</th>
                        <th class="px-4 py-3 font-medium">種別</th>
                        <th class="px-4 py-3 font-medium">コピー回数</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="example in examples.data"
                        :key="example.id"
                        class="border-b border-gray-100 last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    route('admin.color-examples.edit', example.id)
                                "
                                class="text-gray-900 underline hover:text-gray-600"
                            >
                                {{ example.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ TYPE_LABELS[example.type] }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ example.copy_count }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="examples.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in examples.links"
                :key="link.label"
                :href="link.url ?? ''"
                :class="[
                    'rounded-md px-3 py-1.5 text-sm',
                    link.active
                        ? 'bg-gray-900 text-white'
                        : 'text-gray-700 hover:bg-gray-100',
                    link.url === null && 'pointer-events-none text-gray-300',
                ]"
            >
                <span v-html="link.label"></span>
            </Link>
        </nav>
    </AdminLayout>
</template>
```

- [ ] **Step 10: `AdminLayout.vue`のナビゲーションに追加**

`NAV_ITEMS`配列の`UIパターン集管理`の項目の直後に追加:

```typescript
    {
        label: 'カラー例管理',
        routeName: 'admin.color-examples.index',
        activePattern: 'admin.color-examples.*',
    },
```

- [ ] **Step 11: `composer ci:check`を実行**

Run: `composer ci:check`
Expected: 全項目グリーン

- [ ] **Step 12: コミット**

```bash
git add app/Http/Controllers/Admin/ColorExampleController.php routes/admin.php resources/js/components/admin/ColorExampleForm.vue resources/js/pages/admin/color-examples resources/js/layouts/AdminLayout.vue tests/Feature/Admin/ColorExampleControllerTest.php
git commit -m "feat: add Admin CRUD for color examples"
```

---

## Task 7: ドキュメント更新

**Files:**
- Modify: `SPEC.md`
- Modify: `TASKS.md`

**Interfaces:**
- Consumes: 完成した機能全体
- Produces: なし(ドキュメントのみ)

- [ ] **Step 1: `SPEC.md`にセクションを追加**

「## 3X. カラー例・UIパターン集のコピー人気度計測(Phase 30)」のようなセクションを追加し、`docs/superpowers/specs/2026-08-28-copy-popularity-design.md`を参照する形で、データモデル(`color_examples`/`copy_events`/`ui_pattern_snippets.copy_count`)・匿名計測の方式(日次ソルト付きIPハッシュ、1日1回)・表示箇所(各ツールページ内・人気順ソート)を簡潔にまとめる。既存セクションの番号は上書きせず、末尾に追記すること。

- [ ] **Step 2: `TASKS.md`に`Phase 30`として追記**

`Phase 29`の直後に、本Planで実施した内容(モデル・サービス・3ツールへのギャラリー追加・UIパターン集の計測・Admin CRUD)を箇条書きでまとめる。既存の「Phase 29」の書式(完了項目に`[x]`)に合わせる。

- [ ] **Step 3: コミット**

```bash
git add SPEC.md TASKS.md
git commit -m "docs: record copy-popularity tracking feature in SPEC.md and TASKS.md"
```
