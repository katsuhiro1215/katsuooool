# コンテンツ基盤(固定ページ・Blog・FAQ) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** About・Service・FAQの固定ページと、DB駆動のBlog(News)機能(公開側の一覧・詳細 + Admin側のフルCRUD)を追加する。

**Architecture:** About/Service/FAQは既存の法務ページ(`legal/Tokushoho.vue`)と同じ静的Inertiaページパターン。Blogのみ新規`posts`テーブルを持ち、公開側は`published_at`で絞り込んだ読み取り専用ビュー、Admin側は既存の`auth:admin`ガード配下にフルCRUDを追加する。Markdown本文はサーバー側(`league/commonmark`)でHTMLに変換してから渡す。

**Tech Stack:** PHP 8.3 / Laravel 13 / Inertia.js v2 / Vue 3 / TypeScript / league/commonmark 2.10

**Spec:** docs/superpowers/specs/2026-08-24-content-platform-design.md

## Global Constraints

- About・Service・FAQは静的ページ(Admin編集機能を持たない)。コード直接編集で本文を管理する。
- Blogの種別(`type`)は`update`・`fix`・`announcement`の3値固定。追加のカテゴリ・タグ機能は実装しない。
- Blog投稿は`published_at`がnullまたは未来日時の場合、公開側の一覧・詳細のどちらからも参照できない(下書き扱い)。
- Markdown→HTML変換は必ずサーバー側(`league/commonmark`)で行う。クライアント側でのMarkdownパースは行わない。
- 既存のAdmin認証(`auth:admin`ガード)をそのまま利用する。新規の認可ロジックは追加しない。
- 新規ツール画面と同様、shadcn-vueは使わずTailwind CSSで直接コンポーネントを組む(既存の`admin/users/*`・`legal/*`ページと同じスタイル)。
- 各タスク完了時に`composer ci:check`(ESLint/Prettier/vue-tsc/Vitest/Pint/PHPStan/Pest)がグリーンであること。

---

### Task 1: Aboutページ

**Files:**
- Create: `app/Http/Controllers/PageController.php`
- Create: `resources/js/pages/About.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/PageControllerTest.php`

**Interfaces:**
- Produces: ルート名`page.about`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/PageControllerTest.php`を新規作成:

```php
<?php

test('the about page renders', function () {
    $response = $this->get(route('page.about'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('About'));
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/PageControllerTest.php`
Expected: FAIL(ルートが存在しない)

- [ ] **Step 3: `PageController`を作成する**

`app/Http/Controllers/PageController.php`:

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function about(): Response
    {
        return Inertia::render('About');
    }
}
```

- [ ] **Step 4: ルートを追加する**

`routes/web.php`の`use`宣言に追加(アルファベット順、`use App\Http\Controllers\LegalController;`の直前):

```php
use App\Http\Controllers\PageController;
```

`Route::get('/legal/cookie-policy', ...)`の直後に追加:

```php
Route::get('/about', [PageController::class, 'about'])->name('page.about');
```

- [ ] **Step 5: `About.vue`を作成する**

`resources/js/pages/About.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';
</script>

<template>
    <Head title="About - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">About</h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 text-sm leading-relaxed text-gray-700"
            >
                <p>
                    katsuoooolは、Web制作に携わる方向けの実用ツール集サイトです。画像圧縮やカラーパレット生成、CSSジェネレーターなど、日々の制作作業でよく使う小さなツールを、ブラウザから会員登録なしですぐに使える形で提供しています。
                </p>
                <p>
                    「ちょっとした変換や生成のために、いちいちアプリを開いたり環境を構築したりするのが面倒」という制作者自身の実感から生まれたサイトです。派手な機能を詰め込むよりも、必要な処理を最短の手順で終えられることを大切にしています。
                </p>
                <p>
                    無料でご利用いただけるツールを中心に据えつつ、より多くのファイルをまとめて処理したい方向けにPremiumプランもご用意しています。詳しくは<a
                        href="/premium"
                        class="underline hover:text-gray-900"
                        >Premiumページ</a
                    >をご覧ください。
                </p>
            </div>
        </main>

        <AppFooter />
    </div>
</template>
```

- [ ] **Step 6: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/PageControllerTest.php`
Expected: 1 test PASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add app/Http/Controllers/PageController.php resources/js/pages/About.vue routes/web.php tests/Feature/PageControllerTest.php
git commit -m "feat: add About page"
```

---

### Task 2: Serviceページ

**Files:**
- Modify: `app/Http/Controllers/PageController.php`
- Create: `resources/js/pages/Service.vue`
- Modify: `routes/web.php`
- Modify: `tests/Feature/PageControllerTest.php`

**Interfaces:**
- Consumes: `resources/js/lib/toolFilter.ts`の`CATEGORY_TABS`(`{ key: string; label: string }[]`、`key === 'all'`を除く7カテゴリ)
- Produces: ルート名`page.service`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/PageControllerTest.php`に追記:

```php
test('the service page renders', function () {
    $response = $this->get(route('page.service'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Service'));
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/PageControllerTest.php`
Expected: 2 tests中、新しい1件がFAIL(ルートが存在しない)

- [ ] **Step 3: `PageController`に`service()`を追加する**

`app/Http/Controllers/PageController.php`の`about()`の直後に追加:

```php
    public function service(): Response
    {
        return Inertia::render('Service');
    }
```

- [ ] **Step 4: ルートを追加する**

`routes/web.php`の`Route::get('/about', ...)`の直後に追加:

```php
Route::get('/service', [PageController::class, 'service'])->name('page.service');
```

- [ ] **Step 5: `Service.vue`を作成する**

`resources/js/pages/Service.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';
import { CATEGORY_TABS } from '@/lib/toolFilter';

const CATEGORIES = CATEGORY_TABS.filter((tab) => tab.key !== 'all');
</script>

<template>
    <Head title="Service - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">Service</h1>
                <p class="mt-2 text-sm text-gray-600">
                    katsuoooolが提供するツールカテゴリのご紹介です。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <li
                    v-for="category in CATEGORIES"
                    :key="category.key"
                    class="rounded-lg border border-gray-200 bg-white p-4"
                >
                    <span class="text-sm font-semibold text-gray-900">{{
                        category.label
                    }}</span>
                </li>
            </ul>

            <p class="mt-8 text-sm text-gray-600">
                すべてのツールは
                <a href="/" class="underline hover:text-gray-900"
                    >トップページ</a
                >の検索・カテゴリタブから探せます。
            </p>
        </main>

        <AppFooter />
    </div>
</template>
```

- [ ] **Step 6: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/PageControllerTest.php`
Expected: 2 tests PASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add app/Http/Controllers/PageController.php resources/js/pages/Service.vue routes/web.php tests/Feature/PageControllerTest.php
git commit -m "feat: add Service page"
```

---

### Task 3: FAQページ

**Files:**
- Create: `app/Http/Controllers/FaqController.php`
- Create: `resources/js/pages/Faq.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/FaqControllerTest.php`

**Interfaces:**
- Produces: ルート名`faq.index`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/FaqControllerTest.php`を新規作成:

```php
<?php

test('the faq page renders', function () {
    $response = $this->get(route('faq.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Faq'));
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/FaqControllerTest.php`
Expected: FAIL(ルートが存在しない)

- [ ] **Step 3: `FaqController`を作成する**

`app/Http/Controllers/FaqController.php`:

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Faq');
    }
}
```

- [ ] **Step 4: ルートを追加する**

`routes/web.php`の`use`宣言に追加(アルファベット順、`use App\Http\Controllers\HomeController;`の直前):

```php
use App\Http\Controllers\FaqController;
```

`Route::get('/service', ...)`の直後に追加:

```php
Route::get('/faq', [FaqController::class, 'index'])->name('faq.index');
```

- [ ] **Step 5: `Faq.vue`を作成する**

`resources/js/pages/Faq.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';

interface FaqItem {
    question: string;
    answer: string;
}

const ITEMS: FaqItem[] = [
    {
        question: '無料でどこまで使えますか?',
        answer: '既存のツールはすべて無料でご利用いただけます。Premiumプランに登録すると、画像系ツールの一度に処理できる枚数が増えたり、レート制限が緩和されたりします。',
    },
    {
        question: 'アップロードしたファイルはどうなりますか?',
        answer: '処理のために一時的にサーバーへ保存されますが、一定時間の経過後に自動的に削除されます。処理結果のダウンロードURLにも有効期限があります。',
    },
    {
        question: '会員登録は必要ですか?',
        answer: 'ほとんどのツールは会員登録なしでご利用いただけます。Premiumプランへの登録やマイページ機能をご利用の場合のみ、会員登録が必要です。',
    },
    {
        question: 'Premiumプランを解約するとどうなりますか?',
        answer: '解約はいつでも可能です。解約後もお支払い済みの期間終了まではPremium機能をご利用いただけます。返金は行っておりません。',
    },
    {
        question: '対応しているファイル形式を教えてください。',
        answer: '画像系ツールは主にJPEG・PNG・WebP(一部AVIFにも対応)を扱います。ツールごとの詳細な対応形式は、各ツールのページをご確認ください。',
    },
];
</script>

<template>
    <Head title="よくある質問 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    よくある質問
                </h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="space-y-3">
                <details
                    v-for="item in ITEMS"
                    :key="item.question"
                    class="rounded-lg border border-gray-200 bg-white p-4"
                >
                    <summary
                        class="cursor-pointer text-sm font-medium text-gray-900"
                    >
                        {{ item.question }}
                    </summary>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700">
                        {{ item.answer }}
                    </p>
                </details>
            </div>
        </main>

        <AppFooter />
    </div>
</template>
```

- [ ] **Step 6: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/FaqControllerTest.php`
Expected: 1 test PASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add app/Http/Controllers/FaqController.php resources/js/pages/Faq.vue routes/web.php tests/Feature/FaqControllerTest.php
git commit -m "feat: add FAQ page"
```

---

### Task 4: `posts`テーブル・`Post`モデル

**Files:**
- Create: `database/migrations/2026_08_24_000001_create_posts_table.php`
- Create: `app/Models/Post.php`
- Test: `tests/Feature/Models/PostTest.php`

**Interfaces:**
- Produces: `Post`モデル(`title: string`, `slug: string`, `type: string`, `body_markdown: string`, `published_at: ?Carbon`)、`Post::TYPES`(`array{'update','fix','announcement'}`)、`Post::published()`ローカルスコープ

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Models/PostTest.php`を新規作成:

```php
<?php

use App\Models\Post;

test('the published scope only includes posts with a past published_at', function () {
    $published = Post::create([
        'title' => '公開済み記事',
        'slug' => 'published-post',
        'type' => 'announcement',
        'body_markdown' => '本文',
        'published_at' => now()->subDay(),
    ]);

    Post::create([
        'title' => '下書き記事',
        'slug' => 'draft-post',
        'type' => 'announcement',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    Post::create([
        'title' => '予約投稿',
        'slug' => 'scheduled-post',
        'type' => 'announcement',
        'body_markdown' => '本文',
        'published_at' => now()->addDay(),
    ]);

    $results = Post::query()->published()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($published->id);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Models/PostTest.php`
Expected: FAIL(`posts`テーブル・`Post`モデルが存在しない)

- [ ] **Step 3: マイグレーションを作成する**

`database/migrations/2026_08_24_000001_create_posts_table.php`:

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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type');
            $table->text('body_markdown');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

- [ ] **Step 4: `Post`モデルを作成する**

`app/Models/Post.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $type
 * @property string $body_markdown
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['title', 'slug', 'type', 'body_markdown', 'published_at'])]
class Post extends Model
{
    /**
     * @var array<int, string>
     */
    public const TYPES = ['update', 'fix', 'announcement'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
```

- [ ] **Step 5: マイグレーションを実行する**

Run: `php artisan migrate`

- [ ] **Step 6: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Models/PostTest.php`
Expected: 1 test PASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add database/migrations/2026_08_24_000001_create_posts_table.php app/Models/Post.php tests/Feature/Models/PostTest.php
git commit -m "feat: add posts table and Post model"
```

---

### Task 5: `MarkdownRenderer`サービス

**Files:**
- Modify: `composer.json`(`league/commonmark`を直接依存に追加)
- Create: `app/Services/MarkdownRenderer.php`
- Test: `tests/Unit/Services/MarkdownRendererTest.php`

**Interfaces:**
- Produces: `MarkdownRenderer::toHtml(string $markdown): string`

- [ ] **Step 1: `league/commonmark`を直接依存に追加する**

Run: `composer require league/commonmark:^2.10`

(既にcomposer.lockに間接依存として存在するため、バージョンは変わらずcomposer.jsonのrequireにのみ追加されるはず。差分を確認すること)

- [ ] **Step 2: 失敗するテストを書く**

`tests/Unit/Services/MarkdownRendererTest.php`を新規作成:

```php
<?php

use App\Services\MarkdownRenderer;

test('converts markdown headings and links to html', function () {
    $renderer = new MarkdownRenderer();

    $html = $renderer->toHtml("# 見出し\n\n[リンク](https://example.com)");

    expect($html)->toContain('<h1>見出し</h1>');
    expect($html)->toContain('<a href="https://example.com">リンク</a>');
});

test('escapes raw html tags in the input', function () {
    $renderer = new MarkdownRenderer();

    $html = $renderer->toHtml('<script>alert(1)</script>');

    expect($html)->not->toContain('<script>alert(1)</script>');
});
```

- [ ] **Step 3: テストを実行して失敗を確認する**

Run: `php artisan test tests/Unit/Services/MarkdownRendererTest.php`
Expected: FAIL(`MarkdownRenderer`クラスが存在しない)

- [ ] **Step 4: `MarkdownRenderer`を作成する**

`app/Services/MarkdownRenderer.php`:

```php
<?php

namespace App\Services;

use League\CommonMark\CommonMarkConverter;

class MarkdownRenderer
{
    public function toHtml(string $markdown): string
    {
        $converter = new CommonMarkConverter();

        return (string) $converter->convert($markdown);
    }
}
```

- [ ] **Step 5: テストを実行して通ることを確認する**

Run: `php artisan test tests/Unit/Services/MarkdownRendererTest.php`
Expected: 2 tests PASS

(CommonMarkConverterはデフォルト設定で生のHTMLタグをエスケープするため、2件目のテストはデフォルト動作のまま通過するはず)

- [ ] **Step 6: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 7: コミットする**

```bash
git add composer.json composer.lock app/Services/MarkdownRenderer.php tests/Unit/Services/MarkdownRendererTest.php
git commit -m "feat: add MarkdownRenderer service backed by league/commonmark"
```

---

### Task 6: Blog公開側(一覧・詳細)

**Files:**
- Create: `app/Http/Controllers/BlogController.php`
- Create: `resources/js/pages/Blog/Index.vue`
- Create: `resources/js/pages/Blog/Show.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/BlogControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Post`(Task 4)、`App\Services\MarkdownRenderer`(Task 5)
- Produces: ルート名`blog.index`・`blog.show`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/BlogControllerTest.php`を新規作成:

```php
<?php

use App\Models\Post;

test('the index page lists only published posts newest first', function () {
    $older = Post::create([
        'title' => '古いお知らせ',
        'slug' => 'old-announcement',
        'type' => 'announcement',
        'body_markdown' => '本文',
        'published_at' => now()->subDays(5),
    ]);

    $newer = Post::create([
        'title' => '新しいお知らせ',
        'slug' => 'new-announcement',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now()->subDay(),
    ]);

    Post::create([
        'title' => '下書き',
        'slug' => 'draft',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->get(route('blog.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Blog/Index')
        ->has('posts.data', 2)
        ->where('posts.data.0.slug', $newer->slug)
        ->where('posts.data.1.slug', $older->slug)
    );
});

test('the show page renders a published post as html', function () {
    Post::create([
        'title' => 'v2.0リリース',
        'slug' => 'v2-0-release',
        'type' => 'update',
        'body_markdown' => "# v2.0\n\n新機能を追加しました。",
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get(route('blog.show', 'v2-0-release'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Blog/Show')
        ->where('post.title', 'v2.0リリース')
        ->has('post.body_html')
    );
});

test('a draft post returns 404 on the show page', function () {
    Post::create([
        'title' => '下書き',
        'slug' => 'draft-post',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->get(route('blog.show', 'draft-post'));

    $response->assertNotFound();
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/BlogControllerTest.php`
Expected: FAIL(ルートが存在しない)

- [ ] **Step 3: `BlogController`を作成する**

`app/Http/Controllers/BlogController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\MarkdownRenderer;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        $posts = Post::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate(10)
            ->through(fn (Post $post) => [
                'title' => $post->title,
                'slug' => $post->slug,
                'type' => $post->type,
                'published_at' => $post->published_at?->toIso8601String(),
                'excerpt' => Str::limit(strip_tags($post->body_markdown), 100),
            ]);

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug, MarkdownRenderer $markdownRenderer): Response
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Blog/Show', [
            'post' => [
                'title' => $post->title,
                'type' => $post->type,
                'published_at' => $post->published_at?->toIso8601String(),
                'body_html' => $markdownRenderer->toHtml($post->body_markdown),
            ],
        ]);
    }
}
```

- [ ] **Step 4: ルートを追加する**

`routes/web.php`の`use`宣言に追加(アルファベット順、`use App\Http\Controllers\FaqController;`の直前):

```php
use App\Http\Controllers\BlogController;
```

`Route::get('/faq', ...)`の直後に追加:

```php
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
```

- [ ] **Step 5: `Blog/Index.vue`を作成する**

`resources/js/pages/Blog/Index.vue`:

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';

interface PostSummary {
    title: string;
    slug: string;
    type: string;
    published_at: string;
    excerpt: string;
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

defineProps<{ posts: Paginated<PostSummary> }>();

const TYPE_LABELS: Record<string, string> = {
    update: 'アップデート',
    fix: '不具合修正',
    announcement: 'お知らせ',
};

const TYPE_BADGE_CLASSES: Record<string, string> = {
    update: 'bg-blue-50 text-blue-700',
    fix: 'bg-red-50 text-red-700',
    announcement: 'bg-gray-100 text-gray-700',
};

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('ja-JP');
}
</script>

<template>
    <Head title="お知らせ - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">お知らせ</h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div v-if="posts.data.length === 0" class="text-sm text-gray-500">
                お知らせはまだありません。
            </div>

            <ul v-else class="space-y-4">
                <li
                    v-for="post in posts.data"
                    :key="post.slug"
                    class="rounded-lg border border-gray-200 bg-white p-5"
                >
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span
                            class="rounded-full px-2 py-0.5 font-medium"
                            :class="TYPE_BADGE_CLASSES[post.type]"
                        >
                            {{ TYPE_LABELS[post.type] }}
                        </span>
                        <span>{{ formatDate(post.published_at) }}</span>
                    </div>
                    <Link
                        :href="`/blog/${post.slug}`"
                        class="mt-2 block text-base font-semibold text-gray-900 hover:underline"
                    >
                        {{ post.title }}
                    </Link>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ post.excerpt }}
                    </p>
                </li>
            </ul>

            <nav v-if="posts.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                <Link
                    v-for="link in posts.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm',
                        link.active
                            ? 'bg-gray-900 text-white'
                            : 'text-gray-700 hover:bg-gray-100',
                        link.url === null &&
                            'pointer-events-none text-gray-300',
                    ]"
                >
                    <span v-html="link.label"></span>
                </Link>
            </nav>
        </main>

        <AppFooter />
    </div>
</template>
```

- [ ] **Step 6: `Blog/Show.vue`を作成する**

`resources/js/pages/Blog/Show.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';

interface PostDetail {
    title: string;
    type: string;
    published_at: string;
    body_html: string;
}

defineProps<{ post: PostDetail }>();

const TYPE_LABELS: Record<string, string> = {
    update: 'アップデート',
    fix: '不具合修正',
    announcement: 'お知らせ',
};

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('ja-JP');
}
</script>

<template>
    <Head :title="`${post.title} - katsuooool`" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a
                    href="/blog"
                    class="text-sm text-gray-500 hover:text-gray-700"
                    >← お知らせ一覧に戻る</a
                >
                <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                    <span>{{ TYPE_LABELS[post.type] }}</span>
                    <span>{{ formatDate(post.published_at) }}</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">
                    {{ post.title }}
                </h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="prose prose-sm max-w-none rounded-lg border border-gray-200 bg-white p-6"
                v-html="post.body_html"
            ></div>
        </main>

        <AppFooter />
    </div>
</template>
```

- [ ] **Step 7: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/BlogControllerTest.php`
Expected: 3 tests PASS

- [ ] **Step 8: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 9: コミットする**

```bash
git add app/Http/Controllers/BlogController.php resources/js/pages/Blog routes/web.php tests/Feature/BlogControllerTest.php
git commit -m "feat: add public blog index and show pages"
```

---

### Task 7: Admin投稿管理(一覧・新規作成)

**Files:**
- Create: `app/Http/Controllers/Admin/PostController.php`
- Create: `resources/js/components/admin/PostForm.vue`
- Create: `resources/js/pages/admin/posts/Index.vue`
- Create: `resources/js/pages/admin/posts/Create.vue`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/PostControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Post`(Task 4)、`actingAsAdmin()`テストヘルパー(既存、`tests/Pest.php`)
- Produces: ルート名`admin.posts.index`・`admin.posts.create`・`admin.posts.store`、`resources/js/components/admin/PostForm.vue`(`form: InertiaForm<PostFormData>`・`submitLabel: string` propsを受け取り`submit`イベントを発火する共有フォームコンポーネント。Task 8の編集画面でも再利用する)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/PostControllerTest.php`を新規作成:

```php
<?php

use App\Models\Post;

test('unauthenticated visitors are redirected to the admin login page', function () {
    $response = $this->get(route('admin.posts.index'));

    $response->assertRedirect(route('admin.login'));
});

test('the post list shows all posts including drafts, newest published first', function () {
    actingAsAdmin();

    $published = Post::create([
        'title' => '公開済み',
        'slug' => 'published',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now()->subDay(),
    ]);

    $draft = Post::create([
        'title' => '下書き',
        'slug' => 'draft',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->get(route('admin.posts.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/posts/Index')
        ->has('posts.data', 2)
    );
});

test('an admin can create a new post', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.posts.store'), [
        'title' => '新しいお知らせ',
        'slug' => 'new-announcement',
        'type' => 'announcement',
        'body_markdown' => '本文です。',
        'published_at' => null,
    ]);

    $response->assertRedirect(route('admin.posts.index'));
    $this->assertDatabaseHas('posts', [
        'slug' => 'new-announcement',
        'title' => '新しいお知らせ',
    ]);
});

test('creating a post requires a valid type', function () {
    actingAsAdmin();

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.posts.store'), [
            'title' => 'タイトル',
            'slug' => 'invalid-type-post',
            'type' => 'invalid',
            'body_markdown' => '本文',
            'published_at' => null,
        ]);

    $response->assertStatus(422);
});

test('creating a post requires a unique slug', function () {
    actingAsAdmin();

    Post::create([
        'title' => '既存記事',
        'slug' => 'existing-slug',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now(),
    ]);

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.posts.store'), [
            'title' => '新規記事',
            'slug' => 'existing-slug',
            'type' => 'update',
            'body_markdown' => '本文',
            'published_at' => null,
        ]);

    $response->assertStatus(422);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Admin/PostControllerTest.php`
Expected: FAIL(ルートが存在しない)

- [ ] **Step 3: `Admin\PostController`を作成する**

`app/Http/Controllers/Admin/PostController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(): Response
    {
        $posts = Post::query()
            ->orderByDesc('published_at')
            ->paginate(20);

        return Inertia::render('admin/posts/Index', [
            'posts' => $posts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/posts/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        Post::create($this->validated($request));

        return Redirect::route('admin.posts.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('posts', 'slug')->ignore($post),
            ],
            'type' => ['required', 'string', Rule::in(Post::TYPES)],
            'body_markdown' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
```

- [ ] **Step 4: ルートを追加する**

`routes/admin.php`の`use`宣言に追加:

```php
use App\Http\Controllers\Admin\PostController;
```

`Route::middleware('auth:admin')`グループ内、`Route::get('users/{user}', ...)`の直後に追加:

```php
Route::get('posts', [PostController::class, 'index'])->name('posts.index');
Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
Route::post('posts', [PostController::class, 'store'])->name('posts.store');
```

- [ ] **Step 5: 共有フォームコンポーネント`PostForm.vue`を作成する**

`resources/js/components/admin/PostForm.vue`:

```vue
<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

interface PostFormData {
    title: string;
    slug: string;
    type: string;
    body_markdown: string;
    published_at: string;
}

defineProps<{
    form: InertiaForm<PostFormData>;
    submitLabel: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const TYPE_OPTIONS = [
    { value: 'update', label: 'アップデート' },
    { value: 'fix', label: '不具合修正' },
    { value: 'announcement', label: 'お知らせ' },
];
</script>

<template>
    <form class="space-y-4" @submit.prevent="emit('submit')">
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
            <label class="block text-sm font-medium text-gray-700" for="slug"
                >スラッグ(URL用、英数字とハイフンのみ)</label
            >
            <input
                id="slug"
                v-model="form.slug"
                type="text"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">
                {{ form.errors.slug }}
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700" for="type"
                >種別</label
            >
            <select
                id="type"
                v-model="form.type"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                <option
                    v-for="option in TYPE_OPTIONS"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <p v-if="form.errors.type" class="mt-1 text-xs text-red-600">
                {{ form.errors.type }}
            </p>
        </div>

        <div>
            <label
                class="block text-sm font-medium text-gray-700"
                for="body_markdown"
                >本文(Markdown)</label
            >
            <textarea
                id="body_markdown"
                v-model="form.body_markdown"
                rows="12"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <p
                v-if="form.errors.body_markdown"
                class="mt-1 text-xs text-red-600"
            >
                {{ form.errors.body_markdown }}
            </p>
        </div>

        <div>
            <label
                class="block text-sm font-medium text-gray-700"
                for="published_at"
                >公開日時(空欄なら下書き)</label
            >
            <input
                id="published_at"
                v-model="form.published_at"
                type="datetime-local"
                class="mt-1 w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <p
                v-if="form.errors.published_at"
                class="mt-1 text-xs text-red-600"
            >
                {{ form.errors.published_at }}
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

- [ ] **Step 6: `admin/posts/Index.vue`を作成する**

`resources/js/pages/admin/posts/Index.vue`:

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface PostRow {
    id: number;
    title: string;
    slug: string;
    type: string;
    published_at: string | null;
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

defineProps<{ posts: Paginated<PostRow> }>();

const TYPE_LABELS: Record<string, string> = {
    update: 'アップデート',
    fix: '不具合修正',
    announcement: 'お知らせ',
};

function statusLabel(publishedAt: string | null): string {
    if (publishedAt === null) {
        return '下書き';
    }

    return new Date(publishedAt) <= new Date() ? '公開中' : '予約公開';
}
</script>

<template>
    <Head title="投稿管理 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">投稿管理</h1>
                <Link
                    :href="route('admin.posts.create')"
                    class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                >
                    新規作成
                </Link>
            </div>
        </template>

        <div v-if="posts.data.length === 0" class="text-sm text-gray-500">
            投稿がありません。
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
                        <th class="px-4 py-3 font-medium">状態</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="post in posts.data"
                        :key="post.id"
                        class="border-b border-gray-100 last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="route('admin.posts.edit', post.id)"
                                class="text-gray-900 underline hover:text-gray-600"
                            >
                                {{ post.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ TYPE_LABELS[post.type] }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ statusLabel(post.published_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="posts.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in posts.links"
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

**注記:** `admin.posts.edit`ルートはTask 8で追加される。本タスク完了時点ではこのリンクは未定義のルート名を参照するため、`route()`ヘルパー呼び出し自体はビルド時エラーにならないが(Wayfinderは静的解析でルート存在を検証しない)、Task 8完了までは実際にクリックすると実行時エラーになる。Task 7単体の`composer ci:check`(ESLint/vue-tsc/Pest)はこの参照によって失敗しないことを確認する。

- [ ] **Step 7: `admin/posts/Create.vue`を作成する**

`resources/js/pages/admin/posts/Create.vue`:

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PostForm from '@/components/admin/PostForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const form = useForm({
    title: '',
    slug: '',
    type: 'announcement',
    body_markdown: '',
    published_at: '',
});

function submit() {
    form.post(route('admin.posts.store'));
}
</script>

<template>
    <Head title="投稿を新規作成 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">投稿を新規作成</h1>
        </template>

        <PostForm :form="form" submit-label="作成する" @submit="submit" />
    </AdminLayout>
</template>
```

- [ ] **Step 8: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Admin/PostControllerTest.php`
Expected: 5 tests PASS

- [ ] **Step 9: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 10: コミットする**

```bash
git add app/Http/Controllers/Admin/PostController.php resources/js/components/admin/PostForm.vue resources/js/pages/admin/posts/Index.vue resources/js/pages/admin/posts/Create.vue routes/admin.php tests/Feature/Admin/PostControllerTest.php
git commit -m "feat: add admin post list and create screens"
```

---

### Task 8: Admin投稿管理(編集・削除)

**Files:**
- Modify: `app/Http/Controllers/Admin/PostController.php`
- Create: `resources/js/pages/admin/posts/Edit.vue`
- Modify: `routes/admin.php`
- Modify: `tests/Feature/Admin/PostControllerTest.php`

**Interfaces:**
- Consumes: `resources/js/components/admin/PostForm.vue`(Task 7)
- Produces: ルート名`admin.posts.edit`・`admin.posts.update`・`admin.posts.destroy`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/PostControllerTest.php`に追記:

```php
test('the edit page shows the existing post data', function () {
    actingAsAdmin();

    $post = Post::create([
        'title' => '編集対象',
        'slug' => 'editable-post',
        'type' => 'fix',
        'body_markdown' => '既存の本文',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get(route('admin.posts.edit', $post));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/posts/Edit')
        ->where('post.title', '編集対象')
        ->where('post.slug', 'editable-post')
    );
});

test('an admin can update a post', function () {
    actingAsAdmin();

    $post = Post::create([
        'title' => '更新前',
        'slug' => 'update-target',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->put(route('admin.posts.update', $post), [
        'title' => '更新後',
        'slug' => 'update-target',
        'type' => 'update',
        'body_markdown' => '更新後の本文',
        'published_at' => null,
    ]);

    $response->assertRedirect(route('admin.posts.index'));
    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'title' => '更新後',
        'type' => 'update',
    ]);
});

test('updating a post can keep its own slug without triggering a uniqueness error', function () {
    actingAsAdmin();

    $post = Post::create([
        'title' => 'タイトル',
        'slug' => 'same-slug',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now(),
    ]);

    $response = $this->put(route('admin.posts.update', $post), [
        'title' => 'タイトル(更新)',
        'slug' => 'same-slug',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response->assertRedirect(route('admin.posts.index'));
});

test('an admin can delete a post', function () {
    actingAsAdmin();

    $post = Post::create([
        'title' => '削除対象',
        'slug' => 'to-be-deleted',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->delete(route('admin.posts.destroy', $post));

    $response->assertRedirect(route('admin.posts.index'));
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Admin/PostControllerTest.php`
Expected: 新規4件がFAIL(ルートが存在しない)

- [ ] **Step 3: `Admin\PostController`に`edit`・`update`・`destroy`を追加する**

`app/Http/Controllers/Admin/PostController.php`の`store()`の直後に追加:

```php
    public function edit(Post $post): Response
    {
        return Inertia::render('admin/posts/Edit', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'type' => $post->type,
                'body_markdown' => $post->body_markdown,
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $post->update($this->validated($request, $post));

        return Redirect::route('admin.posts.index');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return Redirect::route('admin.posts.index');
    }
```

- [ ] **Step 4: ルートを追加する**

`routes/admin.php`の`Route::post('posts', ...)`の直後に追加:

```php
Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
```

- [ ] **Step 5: `admin/posts/Edit.vue`を作成する**

`resources/js/pages/admin/posts/Edit.vue`:

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import PostForm from '@/components/admin/PostForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface PostDetail {
    id: number;
    title: string;
    slug: string;
    type: string;
    body_markdown: string;
    published_at: string | null;
}

const props = defineProps<{ post: PostDetail }>();

const form = useForm({
    title: props.post.title,
    slug: props.post.slug,
    type: props.post.type,
    body_markdown: props.post.body_markdown,
    published_at: props.post.published_at ?? '',
});

function submit() {
    form.put(route('admin.posts.update', props.post.id));
}

function destroy() {
    if (confirm('この投稿を削除しますか?')) {
        router.delete(route('admin.posts.destroy', props.post.id));
    }
}
</script>

<template>
    <Head title="投稿を編集 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">投稿を編集</h1>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    @click="destroy"
                >
                    削除する
                </button>
            </div>
        </template>

        <PostForm :form="form" submit-label="更新する" @submit="submit" />
    </AdminLayout>
</template>
```

- [ ] **Step 6: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Admin/PostControllerTest.php`
Expected: 9 tests PASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add app/Http/Controllers/Admin/PostController.php resources/js/pages/admin/posts/Edit.vue routes/admin.php tests/Feature/Admin/PostControllerTest.php
git commit -m "feat: add admin post edit and delete"
```

---

### Task 9: ナビゲーション更新(AdminLayout・AppFooter)

**Files:**
- Modify: `resources/js/layouts/AdminLayout.vue`
- Modify: `resources/js/components/AppFooter.vue`

**Interfaces:**
- Consumes: ルート名`admin.posts.index`(Task 7)、`page.about`・`page.service`(Task 1・2)、`faq.index`(Task 3)、`blog.index`(Task 6)

- [ ] **Step 1: `AdminLayout.vue`の`NAV_ITEMS`に投稿管理を追加する**

`resources/js/layouts/AdminLayout.vue`の`NAV_ITEMS`配列を以下に変更:

```typescript
const NAV_ITEMS: NavItem[] = [
    { label: 'ダッシュボード', routeName: 'admin.dashboard' },
    {
        label: 'User管理',
        routeName: 'admin.users.index',
        activePattern: 'admin.users.*',
    },
    {
        label: '投稿管理',
        routeName: 'admin.posts.index',
        activePattern: 'admin.posts.*',
    },
];
```

- [ ] **Step 2: `AppFooter.vue`にコンテンツ系リンクを追加する**

`resources/js/components/AppFooter.vue`を以下に変更:

```vue
<script setup lang="ts">
const CONTENT_LINKS = [
    { label: 'About', href: '/about' },
    { label: 'Service', href: '/service' },
    { label: 'よくある質問', href: '/faq' },
    { label: 'お知らせ', href: '/blog' },
];

const LEGAL_LINKS = [
    { label: '特定商取引法に基づく表記', href: '/legal/tokushoho' },
    { label: 'プライバシーポリシー', href: '/legal/privacy-policy' },
    { label: '利用規約', href: '/legal/terms' },
    { label: 'クッキーポリシー', href: '/legal/cookie-policy' },
];
</script>

<template>
    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div
                class="flex flex-col gap-4 text-sm text-gray-500 sm:flex-row sm:items-start sm:justify-between"
            >
                <p>&copy; katsuooool</p>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:gap-8"
                >
                    <nav class="flex flex-wrap gap-x-4 gap-y-2">
                        <a
                            v-for="link in CONTENT_LINKS"
                            :key="link.href"
                            :href="link.href"
                            class="hover:text-gray-700"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                    <nav class="flex flex-wrap gap-x-4 gap-y-2">
                        <a
                            v-for="link in LEGAL_LINKS"
                            :key="link.href"
                            :href="link.href"
                            class="hover:text-gray-700"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    </footer>
</template>
```

**注記:** `AppFooter`の親コンテナが`max-w-7xl`に変わるため、`Home.vue`側の`<AppFooter />`呼び出し自体は変更不要(コンポーネント内で幅を制御しているため)。ただし`Premium.vue`など`max-w-3xl`ページに配置した場合、フッター内側の実効幅がページ本体より広くなる。これは意図した挙動(フッターはページ内容の幅に関わらずサイト全体で統一した最大幅を持つ)であり、修正不要。

- [ ] **Step 3: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 4: コミットする**

```bash
git add resources/js/layouts/AdminLayout.vue resources/js/components/AppFooter.vue
git commit -m "feat: add content pages navigation to admin sidebar and site footer"
```

---

### Task 10: 全体の最終確認とドキュメント更新

**Files:**
- Modify: `TASKS.md`
- Modify: `SPEC.md`

**Interfaces:**
- Consumes: なし(Task 1〜9の完了を前提とする)

- [ ] **Step 1: 全体のテストスイートを実行する**

Run: `composer ci:check`
Expected: 全項目(Pint/PHPStan/Pest/ESLint/Prettier/vue-tsc/Vitest)がグリーン

- [ ] **Step 2: Playwrightで実ブラウザから一連の動作を確認する**

Playwright MCPツールを使って手動で確認する:

1. `/about`・`/service`・`/faq`が正しく表示される
2. トップページ・Premiumページのフッターに、コンテンツ系4リンク+法務系4リンクが表示され、それぞれ正しい遷移先に飛ぶ
3. Admin(`actingAsAdmin`相当のログイン)で`/admin/posts`にアクセスし、「新規作成」から投稿を作成できる
4. 作成した投稿の`published_at`を過去日時にして公開し、`/blog`一覧・`/blog/{slug}`詳細で正しくMarkdownがHTML変換されて表示されることを確認する
5. `published_at`を空にした投稿(下書き)が`/blog`一覧に出ないこと、直接URLでアクセスすると404になることを確認する
6. Admin側で投稿を編集・削除できることを確認する

- [ ] **Step 3: `TASKS.md`にPhase 22として実装内容を追記する**

`TASKS.md`の「今後の実装順序」セクションの直前に、Phase 22のセクションを追加し、Task 1〜9で実装した内容(About・Service・FAQ・Blog公開側・Admin投稿管理・ナビゲーション更新)を`- [x]`のチェックリストとして記録する。「今後の実装順序」は「現時点で計画済みタスクは全て完了」のまま維持する(次のサブプロジェクトは別途ブレインストーミングで決定するため)。

- [ ] **Step 4: `SPEC.md`の該当箇所を更新する**

- §4.2(新規テーブル)に`posts`テーブルの説明を追加する
- 新しいセクション番号でAbout/Service/FAQ/Blog仕様の要約を追記する(詳細は本Planと`docs/superpowers/specs/2026-08-24-content-platform-design.md`を参照する形でよい)

- [ ] **Step 5: コミットする**

```bash
git add TASKS.md SPEC.md
git commit -m "docs: record content platform implementation in TASKS.md and SPEC.md"
```

- [ ] **Step 6: リモートにpushする**

```bash
git push
```
