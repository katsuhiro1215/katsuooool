# UIパターン集(ボタン集) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/ui-pattern-button`の生成ツール下に、あらかじめ用意した多彩なボタンデザインをGridで並べて表示するギャラリーを追加し、Admin側からコピー用HTML/CSSパターンを管理できるようにする。

**Architecture:** `ui_pattern_snippets`テーブル(`category`列で種別を区別する共通テーブル、将来ボタン以外のUI種別にも同じ仕組みを再利用する)を新設。公開側は各パターンを`<iframe sandbox="">`で個別に隔離してプレビューし、カードごとにHTML/CSSタブを切り替えられるようにする。Admin側はBlog投稿管理(`Admin\PostController`・`PostForm.vue`)と同じ設計パターンを踏襲したフルCRUD。

**Tech Stack:** PHP 8.3 / Laravel 13 / Inertia.js v2 / Vue 3 / TypeScript

**Spec:** docs/superpowers/specs/2026-08-25-ui-pattern-gallery-design.md

## Global Constraints

- `category`は`UiPatternSnippet::CATEGORIES`(初期値`['button']`)に含まれる値のみ許可。新しいUI種別を追加する際は、この配列・Admin側`SnippetForm.vue`の選択肢・該当する公開ツールコントローラーの`extraProps()`オーバーライドをそれぞれ追加する(今回のスコープはボタンのみ)。
- 並び替え機能(`sort_order`等)は実装しない。表示順は常に`id`昇順(作成順)。
- wishlist投票機能は実装しない。
- 各パターンのプレビューは必ず`<iframe sandbox="">`(空のsandbox属性)で隔離する。スクリプト実行を許可する理由はないため、`allow-scripts`等は付与しない。
- 公開側ギャラリーとAdmin側のライブプレビューは、同じ`buildPatternDocument()`ヘルパー(`resources/js/lib/uiPatternSnippet.ts`)を再利用する。
- 既存の`TrackedToolController`を継承する他のColor/Web系ツール(Color Convert等)は、`extraProps()`のデフォルト実装(`[]`を返す)により一切影響を受けない。
- 新規フォームコンポーネント(`SnippetForm.vue`)は、`PostForm.vue`で承認済みの`eslint-disable vue/no-mutating-props`パターン(InertiaFormオブジェクトの直接ミューテート)を踏襲する。
- 各タスク完了時に`composer ci:check`(ESLint/Prettier/vue-tsc/Vitest/Pint/PHPStan/Pest)がグリーンであること。

---

### Task 1: `ui_pattern_snippets`テーブル・`UiPatternSnippet`モデル

**Files:**
- Create: `database/migrations/2026_08_25_000001_create_ui_pattern_snippets_table.php`
- Create: `app/Models/UiPatternSnippet.php`
- Test: `tests/Feature/Models/UiPatternSnippetTest.php`

**Interfaces:**
- Produces: `UiPatternSnippet`モデル(`category: string`, `title: string`, `html: string`, `css: string`)、`UiPatternSnippet::CATEGORIES`(`array{'button'}`)、`UiPatternSnippet::scopeForCategory(Builder $query, string $category): Builder`(categoryで絞り込み、id昇順)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Models/UiPatternSnippetTest.php`を新規作成:

```php
<?php

use App\Models\UiPatternSnippet;

test('the forCategory scope filters by category and orders by id ascending', function () {
    UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'カードパターン',
        'html' => '<div>card</div>',
        'css' => '.card {}',
    ]);

    $createdFirst = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '先に作成したボタン',
        'html' => '<a>first</a>',
        'css' => 'a {}',
    ]);

    $createdSecond = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '後に作成したボタン',
        'html' => '<a>second</a>',
        'css' => 'a {}',
    ]);

    $results = UiPatternSnippet::query()->forCategory('button')->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('id')->all())->toBe([$createdFirst->id, $createdSecond->id]);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Models/UiPatternSnippetTest.php`
Expected: FAIL(`ui_pattern_snippets`テーブル・`UiPatternSnippet`モデルが存在しない)

- [ ] **Step 3: マイグレーションを作成する**

`database/migrations/2026_08_25_000001_create_ui_pattern_snippets_table.php`:

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
        Schema::create('ui_pattern_snippets', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('title');
            $table->text('html');
            $table->text('css');
            $table->timestamps();

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ui_pattern_snippets');
    }
};
```

- [ ] **Step 4: `UiPatternSnippet`モデルを作成する**

`app/Models/UiPatternSnippet.php`:

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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['category', 'title', 'html', 'css'])]
class UiPatternSnippet extends Model
{
    /**
     * @var array<int, string>
     */
    public const CATEGORIES = ['button'];

    /**
     * @param  Builder<UiPatternSnippet>  $query
     * @return Builder<UiPatternSnippet>
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category)->orderBy('id');
    }
}
```

- [ ] **Step 5: マイグレーションを実行する**

Run: `php artisan migrate`

- [ ] **Step 6: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Models/UiPatternSnippetTest.php`
Expected: 1 test PASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add database/migrations/2026_08_25_000001_create_ui_pattern_snippets_table.php app/Models/UiPatternSnippet.php tests/Feature/Models/UiPatternSnippetTest.php
git commit -m "feat: add ui_pattern_snippets table and UiPatternSnippet model"
```

---

### Task 2: `buildPatternDocument()`ヘルパー

**Files:**
- Create: `resources/js/lib/uiPatternSnippet.ts`
- Test: `resources/js/lib/uiPatternSnippet.test.ts`

**Interfaces:**
- Produces: `buildPatternDocument(html: string, css: string): string`(Task 3のPatternCard.vue・Task 4のSnippetForm.vueで再利用する)

- [ ] **Step 1: 失敗するテストを書く**

`resources/js/lib/uiPatternSnippet.test.ts`を新規作成:

```typescript
import { expect, test } from 'vitest';

import { buildPatternDocument } from './uiPatternSnippet';

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
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `npm run test -- uiPatternSnippet`
Expected: FAIL(`uiPatternSnippet.ts`が存在しない)

- [ ] **Step 3: `buildPatternDocument()`を実装する**

`resources/js/lib/uiPatternSnippet.ts`:

```typescript
export function buildPatternDocument(html: string, css: string): string {
    return `<!doctype html>
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
  ${css}
</style>
</head>
<body>
${html}
</body>
</html>`;
}
```

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `npm run test -- uiPatternSnippet`
Expected: 1 test PASS

- [ ] **Step 5: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 6: コミットする**

```bash
git add resources/js/lib/uiPatternSnippet.ts resources/js/lib/uiPatternSnippet.test.ts
git commit -m "feat: add buildPatternDocument helper for sandboxed pattern previews"
```

---

### Task 3: 公開側ギャラリー(`/ui-pattern-button`)

**Files:**
- Modify: `app/Http/Controllers/Tools/Concerns/TrackedToolController.php`
- Modify: `app/Http/Controllers/Tools/UiPatternButtonController.php`
- Create: `resources/js/components/tools/PatternCard.vue`
- Modify: `resources/js/pages/UiPatternButton.vue`
- Test: `tests/Feature/Tools/UiPatternButtonControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\UiPatternSnippet`(Task 1)、`buildPatternDocument()`(Task 2)
- Produces: `TrackedToolController::extraProps(): array`(サブクラスがオーバーライド可能なフック、デフォルトは`[]`)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/UiPatternButtonControllerTest.php`を新規作成:

```php
<?php

use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the show page only includes button-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'カードパターン',
        'html' => '<div>card</div>',
        'css' => '.card {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '斜線シャドウボタン',
        'html' => '<a href="#"><span>Button</span></a>',
        'css' => 'a { display: block; }',
    ]);

    $response = $this->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternButton')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', '斜線シャドウボタン')
    );
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Tools/UiPatternButtonControllerTest.php`
Expected: FAIL(`patterns` propが存在しない)

- [ ] **Step 3: `TrackedToolController`に`extraProps()`フックを追加する**

`app/Http/Controllers/Tools/Concerns/TrackedToolController.php`の`show()`メソッドを以下に置き換える:

```php
    public function show(): Response
    {
        return Inertia::render($this->page(), $this->extraProps());
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [];
    }
```

- [ ] **Step 4: `UiPatternButtonController`に`extraProps()`を追加する**

`app/Http/Controllers/Tools/UiPatternButtonController.php`を以下に置き換える:

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;
use App\Models\UiPatternSnippet;

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
    protected function extraProps(): array
    {
        return [
            'patterns' => UiPatternSnippet::query()
                ->forCategory('button')
                ->get(['id', 'title', 'html', 'css']),
        ];
    }
}
```

- [ ] **Step 5: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Tools/UiPatternButtonControllerTest.php`
Expected: 1 test PASS

- [ ] **Step 6: 他のTrackedToolController継承ツールに影響がないことを確認する**

Run: `php artisan test --filter=ColorConvert`
Expected: 既存テストが全てPASS(`extraProps()`のデフォルト実装により影響なし)

- [ ] **Step 7: `PatternCard.vue`を作成する**

`resources/js/components/tools/PatternCard.vue`:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';

import { buildPatternDocument } from '@/lib/uiPatternSnippet';

const props = defineProps<{
    title: string;
    html: string;
    css: string;
}>();

const activeTab = ref<'html' | 'css'>('html');
const copied = ref(false);

const srcdoc = computed(() => buildPatternDocument(props.html, props.css));

const activeCode = computed(() =>
    activeTab.value === 'html' ? props.html : props.css,
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
    <div class="rounded-lg border border-gray-200 bg-white">
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
        </div>
    </div>
</template>
```

- [ ] **Step 8: `UiPatternButton.vue`にギャラリーを追加する**

`resources/js/pages/UiPatternButton.vue`の`<script setup>`冒頭のimport部分を、以下の内容に置き換える(既存の`import { Head } from '@inertiajs/vue3';`〜`import type { ButtonSize } from '@/lib/uiPatternGenerators';`の5行をまるごと置き換える形):

```typescript
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import PatternCard from '@/components/tools/PatternCard.vue';
import { buildButton } from '@/lib/uiPatternGenerators';
import type { ButtonSize } from '@/lib/uiPatternGenerators';
```

続けて、`const backgroundColor = ref('#2563eb');`より前に、`patterns` propの受け取りを追加する:

```typescript
interface PatternSnippet {
    id: number;
    title: string;
    html: string;
    css: string;
}

const props = defineProps<{ patterns: PatternSnippet[] }>();
```

header内の`<div class="mx-auto max-w-3xl px-6 py-10">`を`<div class="mx-auto max-w-7xl px-6 py-10">`に変更する。

main要素の`<main class="mx-auto max-w-3xl px-6 py-10">`を`<main class="mx-auto max-w-7xl px-6 py-10">`に変更する。

`</main>`閉じタグの直前(既存のCSS出力の`<div class="mt-6">...</div>`ブロックの後)に、以下のギャラリーセクションを追加する:

```vue
            <section v-if="props.patterns.length > 0" class="mt-16">
                <h2 class="text-lg font-bold text-gray-900">いろんなボタン</h2>
                <p class="mt-1 text-sm text-gray-600">
                    気に入ったものをそのままコピーして使えます。
                </p>
                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <PatternCard
                        v-for="pattern in props.patterns"
                        :key="pattern.id"
                        :title="pattern.title"
                        :html="pattern.html"
                        :css="pattern.css"
                    />
                </div>
            </section>
```

- [ ] **Step 9: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 10: コミットする**

```bash
git add app/Http/Controllers/Tools/Concerns/TrackedToolController.php app/Http/Controllers/Tools/UiPatternButtonController.php resources/js/components/tools/PatternCard.vue resources/js/pages/UiPatternButton.vue tests/Feature/Tools/UiPatternButtonControllerTest.php
git commit -m "feat: add button pattern gallery to /ui-pattern-button"
```

---

### Task 4: Admin投稿管理(一覧・新規作成)

**Files:**
- Create: `app/Http/Controllers/Admin/UiPatternSnippetController.php`
- Create: `resources/js/components/admin/SnippetForm.vue`
- Create: `resources/js/pages/admin/ui-pattern-snippets/Index.vue`
- Create: `resources/js/pages/admin/ui-pattern-snippets/Create.vue`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/UiPatternSnippetControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\UiPatternSnippet`(Task 1)、`buildPatternDocument()`(Task 2)、`actingAsAdmin()`テストヘルパー(既存)
- Produces: ルート名`admin.ui-pattern-snippets.index`・`admin.ui-pattern-snippets.create`・`admin.ui-pattern-snippets.store`、`resources/js/components/admin/SnippetForm.vue`(Task 5のEdit.vueでも再利用する)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/UiPatternSnippetControllerTest.php`を新規作成:

```php
<?php

use App\Models\UiPatternSnippet;

test('unauthenticated visitors are redirected to the admin login page', function () {
    $response = $this->get(route('admin.ui-pattern-snippets.index'));

    $response->assertRedirect(route('admin.login'));
});

test('the snippet list shows all snippets', function () {
    actingAsAdmin();

    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'サンプルボタン',
        'html' => '<a>button</a>',
        'css' => 'a {}',
    ]);

    $response = $this->get(route('admin.ui-pattern-snippets.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/ui-pattern-snippets/Index')
        ->has('snippets.data', 1)
    );
});

test('an admin can create a new snippet', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.ui-pattern-snippets.store'), [
        'category' => 'button',
        'title' => '斜線シャドウボタン',
        'html' => '<a href="#"><span>Button</span></a>',
        'css' => 'a { display: block; }',
    ]);

    $response->assertRedirect(route('admin.ui-pattern-snippets.index'));
    $this->assertDatabaseHas('ui_pattern_snippets', [
        'title' => '斜線シャドウボタン',
    ]);
});

test('creating a snippet requires a valid category', function () {
    actingAsAdmin();

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.ui-pattern-snippets.store'), [
            'category' => 'invalid',
            'title' => 'タイトル',
            'html' => '<a>a</a>',
            'css' => 'a {}',
        ]);

    $response->assertStatus(422);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Admin/UiPatternSnippetControllerTest.php`
Expected: FAIL(ルートが存在しない)

- [ ] **Step 3: `Admin\UiPatternSnippetController`を作成する**

`app/Http/Controllers/Admin/UiPatternSnippetController.php`:

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
        ]);
    }
}
```

- [ ] **Step 4: ルートを追加する**

`routes/admin.php`の`use`宣言に追加:

```php
use App\Http\Controllers\Admin\UiPatternSnippetController;
```

`Route::middleware('auth:admin')`グループ内、`Route::delete('posts/{post}', ...)`の直後に追加:

```php
        Route::get('ui-pattern-snippets', [UiPatternSnippetController::class, 'index'])->name('ui-pattern-snippets.index');
        Route::get('ui-pattern-snippets/create', [UiPatternSnippetController::class, 'create'])->name('ui-pattern-snippets.create');
        Route::post('ui-pattern-snippets', [UiPatternSnippetController::class, 'store'])->name('ui-pattern-snippets.store');
```

- [ ] **Step 5: 共有フォームコンポーネント`SnippetForm.vue`を作成する**

`resources/js/components/admin/SnippetForm.vue`:

```vue
<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import { buildPatternDocument } from '@/lib/uiPatternSnippet';

interface SnippetFormData {
    category: string;
    title: string;
    html: string;
    css: string;
}

const CATEGORY_OPTIONS = [{ value: 'button', label: 'ボタン' }];

const props = defineProps<{
    form: InertiaForm<SnippetFormData>;
    submitLabel: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const previewSrcdoc = computed(() =>
    buildPatternDocument(props.form.html, props.form.css),
);
</script>

<template>
    <!-- eslint-disable vue/no-mutating-props -- InertiaForm is a shared reactive form object designed to be mutated in place via v-model from the parent's useForm() call. -->
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div>
            <label
                class="block text-sm font-medium text-gray-700"
                for="category"
                >種別</label
            >
            <select
                id="category"
                v-model="form.category"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                <option
                    v-for="option in CATEGORY_OPTIONS"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <p v-if="form.errors.category" class="mt-1 text-xs text-red-600">
                {{ form.errors.category }}
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
            <label class="block text-sm font-medium text-gray-700" for="html"
                >HTML</label
            >
            <textarea
                id="html"
                v-model="form.html"
                rows="8"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <p v-if="form.errors.html" class="mt-1 text-xs text-red-600">
                {{ form.errors.html }}
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700" for="css"
                >CSS</label
            >
            <textarea
                id="css"
                v-model="form.css"
                rows="10"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <p v-if="form.errors.css" class="mt-1 text-xs text-red-600">
                {{ form.errors.css }}
            </p>
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700"
                >プレビュー</span
            >
            <div
                class="mt-1 flex h-40 items-center justify-center rounded-md border border-gray-300 bg-gray-50"
            >
                <iframe
                    :srcdoc="previewSrcdoc"
                    sandbox=""
                    class="h-full w-full"
                    title="プレビュー"
                />
            </div>
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

- [ ] **Step 6: `admin/ui-pattern-snippets/Index.vue`を作成する**

`resources/js/pages/admin/ui-pattern-snippets/Index.vue`:

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface SnippetRow {
    id: number;
    category: string;
    title: string;
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

defineProps<{ snippets: Paginated<SnippetRow> }>();
</script>

<template>
    <Head title="UIパターン集管理 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">
                    UIパターン集管理
                </h1>
                <Link
                    :href="route('admin.ui-pattern-snippets.create')"
                    class="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                >
                    新規作成
                </Link>
            </div>
        </template>

        <div v-if="snippets.data.length === 0" class="text-sm text-gray-500">
            パターンがありません。
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
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="snippet in snippets.data"
                        :key="snippet.id"
                        class="border-b border-gray-100 last:border-0"
                    >
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
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ snippet.category }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="snippets.links.length > 3"
            class="mt-4 flex flex-wrap gap-1"
        >
            <Link
                v-for="link in snippets.links"
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

- [ ] **Step 7: `admin/ui-pattern-snippets/Create.vue`を作成する**

`resources/js/pages/admin/ui-pattern-snippets/Create.vue`:

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import SnippetForm from '@/components/admin/SnippetForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const form = useForm({
    category: 'button',
    title: '',
    html: '',
    css: '',
});

function submit() {
    form.post(route('admin.ui-pattern-snippets.store'));
}
</script>

<template>
    <Head title="パターンを新規作成 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">
                パターンを新規作成
            </h1>
        </template>

        <SnippetForm :form="form" submit-label="作成する" @submit="submit" />
    </AdminLayout>
</template>
```

- [ ] **Step 8: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Admin/UiPatternSnippetControllerTest.php`
Expected: 4 tests PASS

- [ ] **Step 9: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 10: コミットする**

```bash
git add app/Http/Controllers/Admin/UiPatternSnippetController.php resources/js/components/admin/SnippetForm.vue resources/js/pages/admin/ui-pattern-snippets/Index.vue resources/js/pages/admin/ui-pattern-snippets/Create.vue routes/admin.php tests/Feature/Admin/UiPatternSnippetControllerTest.php
git commit -m "feat: add admin UI pattern snippet list and create screens"
```

---

### Task 5: Admin投稿管理(編集・削除)

**Files:**
- Modify: `app/Http/Controllers/Admin/UiPatternSnippetController.php`
- Create: `resources/js/pages/admin/ui-pattern-snippets/Edit.vue`
- Modify: `routes/admin.php`
- Modify: `tests/Feature/Admin/UiPatternSnippetControllerTest.php`

**Interfaces:**
- Consumes: `resources/js/components/admin/SnippetForm.vue`(Task 4)
- Produces: ルート名`admin.ui-pattern-snippets.edit`・`admin.ui-pattern-snippets.update`・`admin.ui-pattern-snippets.destroy`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/UiPatternSnippetControllerTest.php`に追記:

```php
test('the edit page shows the existing snippet data', function () {
    actingAsAdmin();

    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '編集対象',
        'html' => '<a>edit</a>',
        'css' => 'a {}',
    ]);

    $response = $this->get(route('admin.ui-pattern-snippets.edit', $snippet));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/ui-pattern-snippets/Edit')
        ->where('snippet.title', '編集対象')
    );
});

test('an admin can update a snippet', function () {
    actingAsAdmin();

    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '更新前',
        'html' => '<a>before</a>',
        'css' => 'a {}',
    ]);

    $response = $this->put(route('admin.ui-pattern-snippets.update', $snippet), [
        'category' => 'button',
        'title' => '更新後',
        'html' => '<a>after</a>',
        'css' => 'a { color: red; }',
    ]);

    $response->assertRedirect(route('admin.ui-pattern-snippets.index'));
    $this->assertDatabaseHas('ui_pattern_snippets', [
        'id' => $snippet->id,
        'title' => '更新後',
    ]);
});

test('an admin can delete a snippet', function () {
    actingAsAdmin();

    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '削除対象',
        'html' => '<a>delete</a>',
        'css' => 'a {}',
    ]);

    $response = $this->delete(route('admin.ui-pattern-snippets.destroy', $snippet));

    $response->assertRedirect(route('admin.ui-pattern-snippets.index'));
    $this->assertDatabaseMissing('ui_pattern_snippets', ['id' => $snippet->id]);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Admin/UiPatternSnippetControllerTest.php`
Expected: 新規3件がFAIL(ルートが存在しない)

- [ ] **Step 3: `Admin\UiPatternSnippetController`に`edit`・`update`・`destroy`を追加する**

`app/Http/Controllers/Admin/UiPatternSnippetController.php`の`store()`の直後に追加:

```php
    public function edit(UiPatternSnippet $snippet): Response
    {
        return Inertia::render('admin/ui-pattern-snippets/Edit', [
            'snippet' => [
                'id' => $snippet->id,
                'category' => $snippet->category,
                'title' => $snippet->title,
                'html' => $snippet->html,
                'css' => $snippet->css,
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
```

- [ ] **Step 4: ルートを追加する**

`routes/admin.php`の`Route::post('ui-pattern-snippets', ...)`の直後に追加:

```php
        Route::get('ui-pattern-snippets/{snippet}/edit', [UiPatternSnippetController::class, 'edit'])->name('ui-pattern-snippets.edit');
        Route::put('ui-pattern-snippets/{snippet}', [UiPatternSnippetController::class, 'update'])->name('ui-pattern-snippets.update');
        Route::delete('ui-pattern-snippets/{snippet}', [UiPatternSnippetController::class, 'destroy'])->name('ui-pattern-snippets.destroy');
```

- [ ] **Step 5: `admin/ui-pattern-snippets/Edit.vue`を作成する**

`resources/js/pages/admin/ui-pattern-snippets/Edit.vue`:

```vue
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import SnippetForm from '@/components/admin/SnippetForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface SnippetDetail {
    id: number;
    category: string;
    title: string;
    html: string;
    css: string;
}

const props = defineProps<{ snippet: SnippetDetail }>();

const form = useForm({
    category: props.snippet.category,
    title: props.snippet.title,
    html: props.snippet.html,
    css: props.snippet.css,
});

function submit() {
    form.put(route('admin.ui-pattern-snippets.update', props.snippet.id));
}

function destroy() {
    if (confirm('このパターンを削除しますか?')) {
        router.delete(
            route('admin.ui-pattern-snippets.destroy', props.snippet.id),
        );
    }
}
</script>

<template>
    <Head title="パターンを編集 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">パターンを編集</h1>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    @click="destroy"
                >
                    削除する
                </button>
            </div>
        </template>

        <SnippetForm :form="form" submit-label="更新する" @submit="submit" />
    </AdminLayout>
</template>
```

- [ ] **Step 6: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Admin/UiPatternSnippetControllerTest.php`
Expected: 7 tests PASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add app/Http/Controllers/Admin/UiPatternSnippetController.php resources/js/pages/admin/ui-pattern-snippets/Edit.vue routes/admin.php tests/Feature/Admin/UiPatternSnippetControllerTest.php
git commit -m "feat: add admin UI pattern snippet edit and delete"
```

---

### Task 6: ナビゲーション更新・最終確認・ドキュメント更新

**Files:**
- Modify: `resources/js/layouts/AdminLayout.vue`
- Modify: `TASKS.md`
- Modify: `SPEC.md`

**Interfaces:**
- Consumes: ルート名`admin.ui-pattern-snippets.index`(Task 4)

- [ ] **Step 1: `AdminLayout.vue`の`NAV_ITEMS`に追加する**

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
    {
        label: 'UIパターン集管理',
        routeName: 'admin.ui-pattern-snippets.index',
        activePattern: 'admin.ui-pattern-snippets.*',
    },
];
```

- [ ] **Step 2: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 3: Playwrightで実ブラウザから一連の動作を確認する**

Playwright MCPツールを使って手動で確認する:

1. Admin(`actingAsAdmin`相当のログイン)で`/admin/ui-pattern-snippets`にアクセスし、「新規作成」から、`sample/btn1/index.html`を参考にしたHTML/CSSでパターンを1件登録する。入力中のライブプレビューiframeが正しく表示されることを確認する。
2. `/ui-pattern-button`にアクセスし、生成ツールの下に登録したパターンがGrid表示され、iframeプレビューが正しく描画されることを確認する。
3. カード内のHTML/CSSタブを切り替え、それぞれのコードが表示されること・コピーボタンが機能することを確認する。
4. ブラウザの開発者ツールで、パターンのCSS(例: `a { display: block; }`)がページ本体の他の`<a>`タグに影響していないこと(iframeによる隔離)を確認する。
5. Admin側で登録したパターンを編集・削除できることを確認する。

- [ ] **Step 4: `TASKS.md`にPhase 24として実装内容を追記する**

`TASKS.md`の「今後の実装順序」セクションの直前に、Phase 24のセクションを追加し、Task 1〜5で実装した内容(`ui_pattern_snippets`テーブル・公開側ギャラリー・Admin CRUD)を`- [x]`のチェックリストとして記録する。

- [ ] **Step 5: `SPEC.md`の該当箇所を更新する**

- §4.2(新規テーブル)に`ui_pattern_snippets`テーブルの説明を追加する
- 新しいセクション番号でUIパターン集(ボタン集)仕様の要約を追記する(詳細は本Planと`docs/superpowers/specs/2026-08-25-ui-pattern-gallery-design.md`を参照する形でよい)

- [ ] **Step 6: コミットする**

```bash
git add resources/js/layouts/AdminLayout.vue TASKS.md SPEC.md
git commit -m "docs: record UI pattern gallery implementation in TASKS.md and SPEC.md"
```

- [ ] **Step 7: リモートにpushする**

```bash
git push
```
