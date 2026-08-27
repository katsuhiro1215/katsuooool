# UIパターン集 Premium限定パターン機能 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** UIパターン集ギャラリー(14カテゴリ)の各パターンをAdminが「Premium限定」に指定できるようにし、非Premium閲覧者にはプレビューのみ見せてHTML/CSSコードをサーバー側で完全に遮蔽する。

**Architecture:** `ui_pattern_snippets`に`is_premium`カラムを追加。`TrackedToolController`に共通の`patternGalleryProps()`ヘルパーを追加し、閲覧者が`User::isPremium()`でない場合、Premium限定パターンの`html`/`css`をレスポンスから除外し、代わりにサーバー側で事前計算した`srcdoc`(完成済みHTMLドキュメント文字列)のみを送る。`PatternCard.vue`はこの`locked`状態を見て、コード表示・コピー機能をアップセル表示に切り替える。

**Tech Stack:** PHP 8.3 / Laravel 13 / Inertia.js v2 / Vue 3 / TypeScript

**Spec:** docs/superpowers/specs/2026-08-27-ui-pattern-premium-lock-design.md

## Global Constraints

- 非Premium閲覧者向けレスポンスには、Premium限定パターンの`html`/`css`キー自体を含めない(見た目のぼかしだけでなくサーバー側で遮蔽する)。
- Premium判定は`$request->user()?->isPremium() ?? false`を使う(既存の`User::isPremium()`をそのまま利用、独自カラムは追加しない)。
- 既存の14カテゴリすべてに共通の仕組みとして実装する(カテゴリごとの個別ロジックを書かない)。
- 既存パターンは全て`is_premium = false`のまま(マイグレーションのdefault値により自動的に無料公開を維持)。
- Pattern(背景)カテゴリへの適用は本計画のスコープ外。
- 各タスク完了時に`composer ci:check`(ESLint/Prettier/vue-tsc/Vitest/Pint/PHPStan/Pest)がグリーンであること。

---

### Task 1: マイグレーション・モデル・`PatternDocumentRenderer`サービス

**Files:**
- Create: `database/migrations/2026_08_27_000001_add_is_premium_to_ui_pattern_snippets_table.php`
- Modify: `app/Models/UiPatternSnippet.php`
- Create: `app/Services/PatternDocumentRenderer.php`
- Test: `tests/Unit/Services/PatternDocumentRendererTest.php`
- Test: `tests/Feature/Models/UiPatternSnippetTest.php`(既存ファイルに追記)

**Interfaces:**
- Produces: `UiPatternSnippet::$is_premium`(bool, cast済み)、`App\Services\PatternDocumentRenderer::build(string $html, string $css): string`(Task 2で使用)

- [ ] **Step 1: 失敗するテストを書く(PatternDocumentRenderer)**

`tests/Unit/Services/PatternDocumentRendererTest.php`を新規作成:

```php
<?php

use App\Services\PatternDocumentRenderer;

test('build embeds the given html inside the body and the css inside a style tag', function () {
    $renderer = new PatternDocumentRenderer;

    $result = $renderer->build('<a href="#">Button</a>', 'a { color: red; }');

    expect($result)->toContain('<a href="#">Button</a>');
    expect($result)->toContain('a { color: red; }');
    expect($result)->toContain('<style>');
    expect($result)->toContain('<!doctype html>');
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Unit/Services/PatternDocumentRendererTest.php`
Expected: FAIL(`App\Services\PatternDocumentRenderer`が存在しない)

- [ ] **Step 3: `PatternDocumentRenderer`を実装する**

`app/Services/PatternDocumentRenderer.php`(新規)。この出力は`resources/js/lib/uiPatternSnippet.ts`の`buildPatternDocument()`と完全に同じ構造にすること(片方を変更したらもう片方も追随させる):

```php
<?php

namespace App\Services;

class PatternDocumentRenderer
{
    public function build(string $html, string $css): string
    {
        return <<<HTML
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<style>
  html, body {
    margin: 0;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    box-sizing: border-box;
  }
  {$css}
</style>
</head>
<body>
{$html}
</body>
</html>
HTML;
    }
}
```

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `php artisan test tests/Unit/Services/PatternDocumentRendererTest.php`
Expected: 1 test PASS

- [ ] **Step 5: 失敗するテストを書く(is_premiumカラム)**

`tests/Feature/Models/UiPatternSnippetTest.php`(既存ファイル)の末尾に追記:

```php
test('a snippet defaults to not being premium', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'デフォルト確認',
        'html' => '<a>test</a>',
        'css' => 'a {}',
    ]);

    expect($snippet->is_premium)->toBeFalse();
});

test('a snippet can be marked as premium', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'Premium確認',
        'html' => '<a>test</a>',
        'css' => 'a {}',
        'is_premium' => true,
    ]);

    expect($snippet->fresh()->is_premium)->toBeTrue();
});
```

- [ ] **Step 6: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Models/UiPatternSnippetTest.php`
Expected: 新規2件がFAIL(`is_premium`カラムが存在しない)

- [ ] **Step 7: マイグレーションを作成する**

`database/migrations/2026_08_27_000001_add_is_premium_to_ui_pattern_snippets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ui_pattern_snippets', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false)->after('css');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ui_pattern_snippets', function (Blueprint $table) {
            $table->dropColumn('is_premium');
        });
    }
};
```

- [ ] **Step 8: モデルに`is_premium`を追加する**

`app/Models/UiPatternSnippet.php`を以下に置き換える:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $category
 * @property string $title
 * @property string $html
 * @property string $css
 * @property bool $is_premium
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['category', 'title', 'html', 'css', 'is_premium'])]
class UiPatternSnippet extends Model
{
    /**
     * @var array<int, string>
     */
    public const CATEGORIES = [
        'button',
        'card',
        'pagination',
        'modal',
        'toast',
        'loading',
        'skeleton',
        'form-input',
        'form-textarea',
        'form-check',
        'form-select',
        'form-group',
        'header',
        'footer',
    ];

    /**
     * @param  Builder<UiPatternSnippet>  $query
     * @return Builder<UiPatternSnippet>
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
        ];
    }
}
```

- [ ] **Step 9: マイグレーションを実行する**

Run: `php artisan migrate`

- [ ] **Step 10: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Models/UiPatternSnippetTest.php tests/Unit/Services/PatternDocumentRendererTest.php`
Expected: 全てPASS

- [ ] **Step 11: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 12: コミットする**

```bash
git add database/migrations/2026_08_27_000001_add_is_premium_to_ui_pattern_snippets_table.php app/Models/UiPatternSnippet.php app/Services/PatternDocumentRenderer.php tests/Unit/Services/PatternDocumentRendererTest.php tests/Feature/Models/UiPatternSnippetTest.php
git commit -m "feat: add is_premium column and PatternDocumentRenderer service"
```

---

### Task 2: `TrackedToolController`共通化 + `UiPatternButtonController`をリファレンス実装として変換

**Files:**
- Modify: `app/Http/Controllers/Tools/Concerns/TrackedToolController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternButtonController.php`
- Test: `tests/Feature/Tools/UiPatternButtonControllerTest.php`(既存ファイルに追記)

**Interfaces:**
- Consumes: `App\Services\PatternDocumentRenderer::build()`(Task 1)、`App\Models\UiPatternSnippet`(`is_premium`列、Task 1)
- Produces: `TrackedToolController::show(Request $request): Response`(シグネチャ変更)、`TrackedToolController::extraProps(Request $request): array`(シグネチャ変更、デフォルト`[]`のまま)、`TrackedToolController::patternGalleryProps(Request $request, string $category): array`(Task 3で13個のコントローラーが呼び出す)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/UiPatternButtonControllerTest.php`(既存ファイル)の末尾に追記。既存の2テスト(ページ描画・trackイベント)は変更しない:

```php
test('a non-premium viewer sees only a rendered srcdoc for a premium-only pattern, never the raw html or css', function () {
    $premiumPattern = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'プレミアムボタン',
        'html' => '<a href="#">Premium</a>',
        'css' => 'a { color: gold; }',
        'is_premium' => true,
    ]);

    $response = $this->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternButton')
        ->has('patterns', 1)
        ->where('patterns.0.id', $premiumPattern->id)
        ->where('patterns.0.locked', true)
        ->has('patterns.0.srcdoc')
        ->missing('patterns.0.html')
        ->missing('patterns.0.css')
    );
});

test('a premium subscriber sees the raw html and css for a premium-only pattern', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_premium',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $premiumPattern = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'プレミアムボタン',
        'html' => '<a href="#">Premium</a>',
        'css' => 'a { color: gold; }',
        'is_premium' => true,
    ]);

    $response = $this->actingAs($user)->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternButton')
        ->where('patterns.0.locked', false)
        ->where('patterns.0.html', $premiumPattern->html)
        ->where('patterns.0.css', $premiumPattern->css)
        ->missing('patterns.0.srcdoc')
    );
});

test('a free pattern is never locked regardless of the viewer', function () {
    $freePattern = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '無料ボタン',
        'html' => '<a href="#">Free</a>',
        'css' => 'a { color: blue; }',
    ]);

    $response = $this->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('patterns.0.locked', false)
        ->where('patterns.0.html', $freePattern->html)
        ->where('patterns.0.css', $freePattern->css)
    );
});
```

このファイルの先頭のuse文に`use App\Models\User;`を追加する(既存は`ToolEvent`・`UiPatternSnippet`・`AssertableInertia`のみ)。

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Tools/UiPatternButtonControllerTest.php`
Expected: 新規3件がFAIL(`locked`/`srcdoc`キーが存在しない)

- [ ] **Step 3: `TrackedToolController`に`patternGalleryProps()`を追加し、`show()`/`extraProps()`のシグネチャを変更する**

`app/Http/Controllers/Tools/Concerns/TrackedToolController.php`を以下に置き換える:

```php
<?php

namespace App\Http\Controllers\Tools\Concerns;

use App\Http\Controllers\Controller;
use App\Models\UiPatternSnippet;
use App\Services\PatternDocumentRenderer;
use App\Services\Tools\ToolEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base for tool controllers whose calculation happens entirely in the
 * browser (Color and Web-text-processing categories) — no file upload or
 * download exists. `track()` exists solely so tool_events (and therefore
 * the Admin dashboard's per-tool execution counts) stay populated for
 * these tools too.
 */
abstract class TrackedToolController extends Controller
{
    abstract protected function slug(): string;

    abstract protected function page(): string;

    public function show(Request $request): Response
    {
        return Inertia::render($this->page(), $this->extraProps($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return [];
    }

    /**
     * Builds the `patterns` prop for a UI pattern gallery category, hiding
     * the raw html/css of premium-only patterns from non-premium viewers.
     *
     * @return array<string, mixed>
     */
    protected function patternGalleryProps(Request $request, string $category): array
    {
        $isPremiumViewer = $request->user()?->isPremium() ?? false;
        $renderer = app(PatternDocumentRenderer::class);

        $patterns = UiPatternSnippet::query()
            ->forCategory($category)
            ->get(['id', 'title', 'html', 'css', 'is_premium'])
            ->map(function (UiPatternSnippet $pattern) use ($isPremiumViewer, $renderer): array {
                $locked = $pattern->is_premium && ! $isPremiumViewer;

                if ($locked) {
                    return [
                        'id' => $pattern->id,
                        'title' => $pattern->title,
                        'locked' => true,
                        'srcdoc' => $renderer->build($pattern->html, $pattern->css),
                    ];
                }

                return [
                    'id' => $pattern->id,
                    'title' => $pattern->title,
                    'locked' => false,
                    'html' => $pattern->html,
                    'css' => $pattern->css,
                ];
            });

        return ['patterns' => $patterns];
    }

    public function track(Request $request, ToolEventLogger $eventLogger): JsonResponse
    {
        $eventLogger->log(
            toolSlug: $this->slug(),
            eventType: 'process',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        return response()->json(['status' => 'ok']);
    }
}
```

- [ ] **Step 4: `UiPatternButtonController`を`patternGalleryProps()`を使う形に変換する**

`app/Http/Controllers/Tools/UiPatternButtonController.php`を以下に置き換える:

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternButtonController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-button';
    }

    protected function page(): string
    {
        return 'UiPatternButton';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'button');
    }
}
```

- [ ] **Step 5: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Tools/UiPatternButtonControllerTest.php`
Expected: 5 tests PASS(既存2件 + 新規3件)

- [ ] **Step 6: 他のTrackedToolController継承ツールに影響がないことを確認する**

`extraProps()`のシグネチャ変更は、オーバーライドしていない約38個のColor/Web系サブクラス(デフォルトの`[]`をそのまま使う)には影響しない。念のため代表的な1つを確認する:

Run: `php artisan test --filter=ColorConvert`
Expected: 既存テストが全てPASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add app/Http/Controllers/Tools/Concerns/TrackedToolController.php app/Http/Controllers/Tools/UiPatternButtonController.php tests/Feature/Tools/UiPatternButtonControllerTest.php
git commit -m "feat: hide premium-only pattern code from non-premium viewers"
```

---

### Task 3: 残り13カテゴリのコントローラーを`patternGalleryProps()`に変換

**Files:**
- Modify: `app/Http/Controllers/Tools/UiPatternCardController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternPaginationController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternModalController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternToastController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternLoadingController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternSkeletonController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternFormInputController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternFormTextareaController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternFormCheckController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternFormSelectController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternFormGroupController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternHeaderController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternFooterController.php`

**Interfaces:**
- Consumes: `TrackedToolController::patternGalleryProps(Request $request, string $category): array`(Task 2)

これは全カテゴリで完全に同一の変換(機械的な繰り返し)。各コントローラーは現在Task 2変換前の`UiPatternButtonController`と同じ形(`extraProps(): array`で`UiPatternSnippet::query()->forCategory('<category>')->get(['id','title','html','css'])`を返すのみ)をしている。以下の表のcategory値を使い、Task 2 Step 4と全く同じ変換を13回適用する。

| category | ファイル |
|---|---|
| card | `app/Http/Controllers/Tools/UiPatternCardController.php` |
| pagination | `app/Http/Controllers/Tools/UiPatternPaginationController.php` |
| modal | `app/Http/Controllers/Tools/UiPatternModalController.php` |
| toast | `app/Http/Controllers/Tools/UiPatternToastController.php` |
| loading | `app/Http/Controllers/Tools/UiPatternLoadingController.php` |
| skeleton | `app/Http/Controllers/Tools/UiPatternSkeletonController.php` |
| form-input | `app/Http/Controllers/Tools/UiPatternFormInputController.php` |
| form-textarea | `app/Http/Controllers/Tools/UiPatternFormTextareaController.php` |
| form-check | `app/Http/Controllers/Tools/UiPatternFormCheckController.php` |
| form-select | `app/Http/Controllers/Tools/UiPatternFormSelectController.php` |
| form-group | `app/Http/Controllers/Tools/UiPatternFormGroupController.php` |
| header | `app/Http/Controllers/Tools/UiPatternHeaderController.php` |
| footer | `app/Http/Controllers/Tools/UiPatternFooterController.php` |

- [ ] **Step 1: 各コントローラーを変換する**

各ファイルについて、既存の`extraProps(): array`メソッド(および`use App\Models\UiPatternSnippet;`のuse文)を削除し、代わりに以下を追加する(`<category>`は上表の値に置き換える)。例(card):

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use Illuminate\Http\Request;

class UiPatternCardController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'ui-pattern-card';
    }

    protected function page(): string
    {
        return 'UiPatternCard';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(Request $request): array
    {
        return $this->patternGalleryProps($request, 'card');
    }
}
```

他の12ファイルも、クラス名・`slug()`の返り値・`page()`の返り値はそのまま変更せず、`extraProps()`の中身だけを`return $this->patternGalleryProps($request, '<category>');`に置き換え、メソッドシグネチャを`extraProps(Request $request): array`にし、`use Illuminate\Http\Request;`をuse文に追加し、`use App\Models\UiPatternSnippet;`のuse文を削除する。

- [ ] **Step 2: 既存テストを実行してグリーンを確認する(回帰確認)**

各カテゴリの既存Featureテスト(ページ描画・trackイベント・カテゴリ絞り込み)が壊れていないことを確認する:

Run: `php artisan test tests/Feature/Tools/`
Expected: 全てPASS(新規テストは追加しない。共通ロジックはTask 2で検証済みのため、13個の代表テストは既存の「カテゴリ絞り込み」テストがそのまま通ることで、`patternGalleryProps()`が従来通り無料パターンを正しく返すことを担保する)

- [ ] **Step 3: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 4: コミットする**

```bash
git add app/Http/Controllers/Tools/UiPatternCardController.php app/Http/Controllers/Tools/UiPatternPaginationController.php app/Http/Controllers/Tools/UiPatternModalController.php app/Http/Controllers/Tools/UiPatternToastController.php app/Http/Controllers/Tools/UiPatternLoadingController.php app/Http/Controllers/Tools/UiPatternSkeletonController.php app/Http/Controllers/Tools/UiPatternFormInputController.php app/Http/Controllers/Tools/UiPatternFormTextareaController.php app/Http/Controllers/Tools/UiPatternFormCheckController.php app/Http/Controllers/Tools/UiPatternFormSelectController.php app/Http/Controllers/Tools/UiPatternFormGroupController.php app/Http/Controllers/Tools/UiPatternHeaderController.php app/Http/Controllers/Tools/UiPatternFooterController.php
git commit -m "feat: apply premium pattern locking to the remaining 13 gallery categories"
```

---

### Task 4: Admin側(バリデーション・チェックボックス・一覧バッジ)

**Files:**
- Modify: `app/Http/Controllers/Admin/UiPatternSnippetController.php`
- Modify: `resources/js/components/admin/SnippetForm.vue`
- Modify: `resources/js/pages/admin/ui-pattern-snippets/Create.vue`
- Modify: `resources/js/pages/admin/ui-pattern-snippets/Edit.vue`
- Modify: `resources/js/pages/admin/ui-pattern-snippets/Index.vue`
- Test: `tests/Feature/Admin/UiPatternSnippetControllerTest.php`(既存ファイルに追記)

**Interfaces:**
- Produces: Admin作成・編集フォームの`is_premium`フィールド、一覧の`SnippetRow.is_premium`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/UiPatternSnippetControllerTest.php`(既存ファイル)の末尾に追記:

```php
test('an admin can create a snippet marked as premium', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.ui-pattern-snippets.store'), [
        'category' => 'button',
        'title' => 'Premiumボタン',
        'html' => '<a href="#">Premium</a>',
        'css' => 'a { color: gold; }',
        'is_premium' => true,
    ]);

    $response->assertRedirect(route('admin.ui-pattern-snippets.index'));
    $this->assertDatabaseHas('ui_pattern_snippets', [
        'title' => 'Premiumボタン',
        'is_premium' => true,
    ]);
});

test('the edit page includes whether the snippet is premium', function () {
    actingAsAdmin();

    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'Premium確認用',
        'html' => '<a>edit</a>',
        'css' => 'a {}',
        'is_premium' => true,
    ]);

    $response = $this->get(route('admin.ui-pattern-snippets.edit', $snippet));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/ui-pattern-snippets/Edit')
        ->where('snippet.is_premium', true)
    );
});

test('an admin can toggle a snippet from premium back to free', function () {
    actingAsAdmin();

    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '切り替え確認',
        'html' => '<a>before</a>',
        'css' => 'a {}',
        'is_premium' => true,
    ]);

    $response = $this->put(route('admin.ui-pattern-snippets.update', $snippet), [
        'category' => 'button',
        'title' => '切り替え確認',
        'html' => '<a>after</a>',
        'css' => 'a {}',
        'is_premium' => false,
    ]);

    $response->assertRedirect(route('admin.ui-pattern-snippets.index'));
    $this->assertDatabaseHas('ui_pattern_snippets', [
        'id' => $snippet->id,
        'is_premium' => false,
    ]);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Admin/UiPatternSnippetControllerTest.php`
Expected: 新規3件がFAIL(`is_premium`がバリデーション・`edit()`レスポンスに存在しない)

- [ ] **Step 3: `Admin\UiPatternSnippetController`を更新する**

`app/Http/Controllers/Admin/UiPatternSnippetController.php`を以下に置き換える:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UiPatternSnippet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UiPatternSnippetController extends Controller
{
    public function index(): Response
    {
        $snippets = UiPatternSnippet::query()
            ->orderBy('category')
            ->orderBy('id')
            ->paginate(20);

        return Inertia::render('admin/ui-pattern-snippets/Index', [
            'snippets' => $snippets,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/ui-pattern-snippets/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        UiPatternSnippet::create($this->validated($request));

        return Redirect::route('admin.ui-pattern-snippets.index');
    }

    public function edit(UiPatternSnippet $snippet): Response
    {
        return Inertia::render('admin/ui-pattern-snippets/Edit', [
            'snippet' => [
                'id' => $snippet->id,
                'category' => $snippet->category,
                'title' => $snippet->title,
                'html' => $snippet->html,
                'css' => $snippet->css,
                'is_premium' => $snippet->is_premium,
            ],
        ]);
    }

    public function update(Request $request, UiPatternSnippet $snippet): RedirectResponse
    {
        $snippet->update($this->validated($request));

        return Redirect::route('admin.ui-pattern-snippets.index');
    }

    public function destroy(UiPatternSnippet $snippet): RedirectResponse
    {
        $snippet->delete();

        return Redirect::route('admin.ui-pattern-snippets.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', Rule::in(UiPatternSnippet::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'html' => ['required', 'string'],
            'css' => ['required', 'string'],
            'is_premium' => ['boolean'],
        ]);
    }
}
```

- [ ] **Step 4: `SnippetForm.vue`にチェックボックスを追加する**

`resources/js/components/admin/SnippetForm.vue`の`SnippetFormData`インターフェースと、送信ボタンの直前の位置を編集する。

`interface SnippetFormData`を以下に置き換える:

```typescript
interface SnippetFormData {
    category: string;
    title: string;
    html: string;
    css: string;
    is_premium: boolean;
}
```

`</div>`(プレビューブロックの閉じタグ)の直後・`<button type="submit"`の直前に、以下を追加する:

```vue
        <div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="form.is_premium" type="checkbox" />
                Premium限定パターンにする
            </label>
        </div>

```

- [ ] **Step 5: `Create.vue`・`Edit.vue`のフォーム初期値に`is_premium`を追加する**

`resources/js/pages/admin/ui-pattern-snippets/Create.vue`の`useForm({...})`を以下に置き換える:

```typescript
const form = useForm({
    category: 'button',
    title: '',
    html: '',
    css: '',
    is_premium: false,
});
```

`resources/js/pages/admin/ui-pattern-snippets/Edit.vue`の`interface SnippetDetail`と`useForm({...})`を以下に置き換える:

```typescript
interface SnippetDetail {
    id: number;
    category: string;
    title: string;
    html: string;
    css: string;
    is_premium: boolean;
}
```

```typescript
const form = useForm({
    category: props.snippet.category,
    title: props.snippet.title,
    html: props.snippet.html,
    css: props.snippet.css,
    is_premium: props.snippet.is_premium,
});
```

- [ ] **Step 6: `Index.vue`にPremiumバッジを追加する**

`resources/js/pages/admin/ui-pattern-snippets/Index.vue`の`interface SnippetRow`を以下に置き換える:

```typescript
interface SnippetRow {
    id: number;
    category: string;
    title: string;
    is_premium: boolean;
}
```

タイトルを表示している`<td>`ブロック(`<Link :href="route('admin.ui-pattern-snippets.edit', snippet.id)" ...>{{ snippet.title }}</Link>`を含む`<td>`)を以下に置き換える:

```vue
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    route(
                                        'admin.ui-pattern-snippets.edit',
                                        snippet.id,
                                    )
                                "
                                class="text-gray-900 underline hover:text-gray-600"
                            >
                                {{ snippet.title }}
                            </Link>
                            <span
                                v-if="snippet.is_premium"
                                class="ml-2 rounded-full bg-gray-900 px-2 py-0.5 text-[10px] font-bold text-white"
                            >
                                Premium
                            </span>
                        </td>
```

- [ ] **Step 7: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Admin/UiPatternSnippetControllerTest.php`
Expected: 10 tests PASS(既存7件 + 新規3件)

- [ ] **Step 8: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 9: コミットする**

```bash
git add app/Http/Controllers/Admin/UiPatternSnippetController.php resources/js/components/admin/SnippetForm.vue resources/js/pages/admin/ui-pattern-snippets/Create.vue resources/js/pages/admin/ui-pattern-snippets/Edit.vue resources/js/pages/admin/ui-pattern-snippets/Index.vue tests/Feature/Admin/UiPatternSnippetControllerTest.php
git commit -m "feat: add is_premium toggle to the Admin snippet form and list"
```

---

### Task 5: `PatternCard.vue`のロック表示

**Files:**
- Modify: `resources/js/components/tools/PatternCard.vue`
- Modify: `resources/js/components/tools/PatternCard.test.ts`

**Interfaces:**
- Produces: `PatternCard`の新しいprops形状 `{ title: string; locked: boolean; html?: string; css?: string; lockedSrcdoc?: string }`(Task 6で全14ページから使用)

**注記:** サーバー側APIのフィールド名は`srcdoc`(設計書通り)だが、`PatternCard.vue`内部では`computed`で既に`srcdoc`という変数名を使っているため、propの名前を`lockedSrcdoc`にして名前の衝突を避ける。呼び出し側(Task 6)では`:locked-srcdoc="pattern.srcdoc"`のように渡す。

- [ ] **Step 1: 失敗するテストを書く**

`resources/js/components/tools/PatternCard.test.ts`を以下に置き換える(既存2テストの`props`に`locked: false`を追加し、ロック時の新規テストを2件追加する):

```typescript
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import PatternCard from './PatternCard.vue';

const html = '<a href="#">Button</a>';
const css = 'a { color: red; }';

test('switches the displayed code from HTML to CSS when the CSS tab is clicked', async () => {
    const wrapper = mount(PatternCard, {
        props: { title: 'サンプルボタン', locked: false, html, css },
    });

    expect(wrapper.find('pre').text()).toBe(html);

    const cssTab = wrapper
        .findAll('button')
        .find((button) => button.text() === 'CSS');
    await cssTab?.trigger('click');

    expect(wrapper.find('pre').text()).toBe(css);
});

test('embeds the css content in the preview iframe srcdoc', () => {
    const wrapper = mount(PatternCard, {
        props: { title: 'サンプルボタン', locked: false, html, css },
    });

    const srcdoc = wrapper.find('iframe').attributes('srcdoc');
    expect(srcdoc).toContain(css);
});

test('a locked pattern shows the server-provided srcdoc without any code tabs or copy button', () => {
    const wrapper = mount(PatternCard, {
        props: {
            title: 'Premiumボタン',
            locked: true,
            lockedSrcdoc: '<!doctype html><html><body>locked preview</body></html>',
        },
    });

    expect(wrapper.find('iframe').attributes('srcdoc')).toBe(
        '<!doctype html><html><body>locked preview</body></html>',
    );
    expect(wrapper.find('pre').exists()).toBe(false);
    expect(
        wrapper.findAll('button').find((button) => button.text() === 'コピー'),
    ).toBeUndefined();
});

test('a locked pattern shows an upsell link to /premium', () => {
    const wrapper = mount(PatternCard, {
        props: {
            title: 'Premiumボタン',
            locked: true,
            lockedSrcdoc: '<!doctype html><html><body>locked preview</body></html>',
        },
    });

    const upsellLink = wrapper.find('a[href="/premium"]');
    expect(upsellLink.exists()).toBe(true);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `npm run test -- PatternCard`
Expected: 既存2件も含めてFAIL(`locked` propが必須になるため型エラー、またはロック時UIが存在しない)

- [ ] **Step 3: `PatternCard.vue`を実装する**

`resources/js/components/tools/PatternCard.vue`を以下に置き換える:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';

import { buildPatternDocument } from '@/lib/uiPatternSnippet';

const props = defineProps<{
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
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}
</script>

<template>
    <div class="relative rounded-lg border border-gray-200 bg-white">
        <span
            v-if="locked"
            class="absolute top-2 right-2 z-10 rounded-full bg-gray-900 px-2 py-0.5 text-[10px] font-bold text-white"
        >
            Premium
        </span>
        <div
            class="flex h-40 items-center justify-center border-b border-gray-200 bg-gray-50"
        >
            <iframe
                :srcdoc="srcdoc"
                sandbox=""
                class="h-full w-full"
                title="プレビュー"
            />
        </div>
        <div class="p-4">
            <p class="mb-2 text-sm font-medium text-gray-900">{{ title }}</p>

            <div
                v-if="locked"
                class="rounded-md border border-gray-200 bg-gray-50 p-3 text-center text-xs text-gray-600"
            >
                <p>このパターンのコードはPremium会員限定です。</p>
                <a
                    href="/premium"
                    class="mt-2 inline-block font-medium text-gray-900 underline"
                >
                    Premiumに登録する
                </a>
            </div>
            <template v-else>
                <div class="mb-2 flex items-center justify-between">
                    <div class="flex gap-1">
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium"
                            :class="
                                activeTab === 'html'
                                    ? 'bg-gray-900 text-white'
                                    : 'text-gray-500 hover:bg-gray-100'
                            "
                            @click="activeTab = 'html'"
                        >
                            HTML
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium"
                            :class="
                                activeTab === 'css'
                                    ? 'bg-gray-900 text-white'
                                    : 'text-gray-500 hover:bg-gray-100'
                            "
                            @click="activeTab = 'css'"
                        >
                            CSS
                        </button>
                    </div>
                    <button
                        type="button"
                        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                        @click="copyCode"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <pre
                    class="overflow-x-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100"
                    >{{ activeCode }}</pre
                >
            </template>
        </div>
    </div>
</template>
```

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `npm run test -- PatternCard`
Expected: 4 tests PASS

- [ ] **Step 5: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 6: コミットする**

```bash
git add resources/js/components/tools/PatternCard.vue resources/js/components/tools/PatternCard.test.ts
git commit -m "feat: add locked-state upsell UI to PatternCard"
```

---

### Task 6: 全14ページの`PatternCard`呼び出しを更新 + 共有型の抽出

**Files:**
- Modify: `resources/js/lib/uiPatternSnippet.ts`
- Modify: `resources/js/lib/uiPatternSnippet.test.ts`
- Modify: `resources/js/pages/UiPatternButton.vue`
- Modify: `resources/js/pages/UiPatternCard.vue`
- Modify: `resources/js/pages/UiPatternPagination.vue`
- Modify: `resources/js/pages/UiPatternModal.vue`
- Modify: `resources/js/pages/UiPatternToast.vue`
- Modify: `resources/js/pages/UiPatternLoading.vue`
- Modify: `resources/js/pages/UiPatternSkeleton.vue`
- Modify: `resources/js/pages/UiPatternFormInput.vue`
- Modify: `resources/js/pages/UiPatternFormTextarea.vue`
- Modify: `resources/js/pages/UiPatternFormCheck.vue`
- Modify: `resources/js/pages/UiPatternFormSelect.vue`
- Modify: `resources/js/pages/UiPatternFormGroup.vue`
- Modify: `resources/js/pages/UiPatternHeader.vue`
- Modify: `resources/js/pages/UiPatternFooter.vue`

**Interfaces:**
- Consumes: `PatternCard`の新しいprops形状(Task 5)
- Produces: `PatternSnippet`型を`resources/js/lib/uiPatternSnippet.ts`からエクスポート(各ページの重複したローカル定義を置き換える)

- [ ] **Step 1: 失敗するテストを書く**

`resources/js/lib/uiPatternSnippet.test.ts`を以下に置き換える(既存テストはそのまま、型エクスポートの存在を保証する軽いテストを追加):

```typescript
import { expect, test } from 'vitest';

import { buildPatternDocument } from './uiPatternSnippet';
import type { PatternSnippet } from './uiPatternSnippet';

test('embeds the given html inside the body and the css inside a style tag', () => {
    const result = buildPatternDocument(
        '<a href="#">Button</a>',
        'a { color: red; }',
    );

    expect(result).toContain('<a href="#">Button</a>');
    expect(result).toContain('a { color: red; }');
    expect(result).toContain('<style>');
    expect(result).toContain('<!doctype html>');
});

test('PatternSnippet accepts both locked and unlocked shapes', () => {
    const unlocked: PatternSnippet = {
        id: 1,
        title: 'サンプル',
        locked: false,
        html: '<a>a</a>',
        css: 'a {}',
    };
    const locked: PatternSnippet = {
        id: 2,
        title: 'Premium',
        locked: true,
        srcdoc: '<html></html>',
    };

    expect(unlocked.locked).toBe(false);
    expect(locked.locked).toBe(true);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `npm run test -- uiPatternSnippet`
Expected: FAIL(`PatternSnippet`がエクスポートされていない)

- [ ] **Step 3: `PatternSnippet`型を追加する**

`resources/js/lib/uiPatternSnippet.ts`の先頭に以下を追加する(既存の`buildPatternDocument`関数はそのまま):

```typescript
export interface PatternSnippet {
    id: number;
    title: string;
    locked: boolean;
    html?: string;
    css?: string;
    srcdoc?: string;
}

```

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `npm run test -- uiPatternSnippet`
Expected: 2 tests PASS

- [ ] **Step 5: 各ページの`PatternCard`呼び出しを更新する**

14個の`resources/js/pages/UiPattern*.vue`ファイルそれぞれについて、以下2つの変更を行う。

(1) ローカルの`interface PatternSnippet { id: number; title: string; html: string; css: string; }`定義を削除し、代わりに`import type { PatternSnippet } from '@/lib/uiPatternSnippet';`をインポート文に追加する(既存の`import PatternCard from '@/components/tools/PatternCard.vue';`の直後に追加する)。`const props = defineProps<{ patterns: PatternSnippet[] }>();`の行自体は変更しない。

(2) `<PatternCard v-for="pattern in props.patterns" :key="pattern.id" :title="pattern.title" :html="pattern.html" :css="pattern.css" />`となっている箇所を、以下に置き換える:

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

対象ファイル(全14件、変換内容は同一):
`UiPatternButton.vue`・`UiPatternCard.vue`・`UiPatternPagination.vue`・`UiPatternModal.vue`・`UiPatternToast.vue`・`UiPatternLoading.vue`・`UiPatternSkeleton.vue`・`UiPatternFormInput.vue`・`UiPatternFormTextarea.vue`・`UiPatternFormCheck.vue`・`UiPatternFormSelect.vue`・`UiPatternFormGroup.vue`・`UiPatternHeader.vue`・`UiPatternFooter.vue`

- [ ] **Step 6: `vue-tsc`で型エラーがないことを確認する**

Run: `npx vue-tsc --noEmit`
Expected: エラーなし

- [ ] **Step 7: 各カテゴリの既存Featureテストを実行してグリーンを確認する(回帰確認)**

Run: `php artisan test tests/Feature/Tools/`
Expected: 全てPASS

- [ ] **Step 8: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 9: コミットする**

```bash
git add resources/js/lib/uiPatternSnippet.ts resources/js/lib/uiPatternSnippet.test.ts resources/js/pages/UiPatternButton.vue resources/js/pages/UiPatternCard.vue resources/js/pages/UiPatternPagination.vue resources/js/pages/UiPatternModal.vue resources/js/pages/UiPatternToast.vue resources/js/pages/UiPatternLoading.vue resources/js/pages/UiPatternSkeleton.vue resources/js/pages/UiPatternFormInput.vue resources/js/pages/UiPatternFormTextarea.vue resources/js/pages/UiPatternFormCheck.vue resources/js/pages/UiPatternFormSelect.vue resources/js/pages/UiPatternFormGroup.vue resources/js/pages/UiPatternHeader.vue resources/js/pages/UiPatternFooter.vue
git commit -m "feat: wire locked-pattern props through all 14 UI pattern gallery pages"
```

---

### Task 7: 最終確認・ドキュメント更新

**Files:**
- Modify: `TASKS.md`
- Modify: `SPEC.md`

**Interfaces:**
- Consumes: 全タスクの完了

- [ ] **Step 1: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 2: Playwrightで実ブラウザから一連の動作を確認する**

Playwright MCPツールを使って手動で確認する:

1. Admin(`actingAsAdmin`相当のログイン)で`/admin/ui-pattern-snippets`にアクセスし、既存パターンを1件編集して「Premium限定パターンにする」にチェックを入れて保存する。一覧に「Premium」バッジが表示されることを確認する。
2. 未ログイン状態で`/ui-pattern-button`(または該当カテゴリのページ)にアクセスし、該当パターンのプレビュー(見た目)は表示されるが、コード欄が「このパターンのコードはPremium会員限定です。」というアップセル表示に置き換わっていること、コピーボタンが存在しないことを確認する。
3. ブラウザの開発者ツールでネットワークタブ・ページソースを確認し、そのパターンの生の`html`/`css`がレスポンスに一切含まれていないことを確認する(核心的な確認項目)。
4. `/premium`へのリンクが機能することを確認する。
5. Premiumサブスクリプションを持つユーザー(テスト用にDBで`subscriptions`テーブルへ直接レコードを追加するか、既存のテスト用Premiumユーザーがいればそれでログイン)で同じページにアクセスし、コード欄・コピーボタンが通常通り表示されることを確認する。

- [ ] **Step 3: `TASKS.md`にPhase 27として実装内容を追記する**

`TASKS.md`の「今後の実装順序」セクションの直前に、Phase 27のセクションを追加し、is_premiumカラム・共通ロジック(`patternGalleryProps()`)・Admin UI・`PatternCard.vue`のロック表示を`- [x]`のチェックリストとして記録する。

- [ ] **Step 4: `SPEC.md`の該当箇所を更新する**

§33(UIパターン集ギャラリー仕様)に、Premium限定パターン機能の概要を追記する(詳細は本Planと`docs/superpowers/specs/2026-08-27-ui-pattern-premium-lock-design.md`を参照する形でよい)。また、§30(Premium/Stripe決済連携仕様)の「Premiumの価値提供」リストに「UIパターン集の一部パターンのコード閲覧」を追加する。

- [ ] **Step 5: コミットする**

```bash
git add TASKS.md SPEC.md
git commit -m "docs: record UI pattern gallery Premium-locked patterns in TASKS.md and SPEC.md"
```

- [ ] **Step 6: リモートにpushする**

```bash
git push
```
