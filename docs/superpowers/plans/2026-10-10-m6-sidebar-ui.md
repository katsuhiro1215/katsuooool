# M6 左サイドバー UI 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 公開側の全ページに左サイドバー（カテゴリ・検索）を付け、トップはカテゴリ別のカード一覧にする。

**Architecture:** ツールのナビ情報を Inertia 共有プロパティ `toolNav` で全ページに渡す。`PublicLayout` が `CategorySidebar` を持つ。現在のカテゴリと検索語は、現在の URL（`usePage().url`）から算出する。

**Tech Stack:** Laravel 13 / Pest、Vue 3 / Inertia v2 / TypeScript、Vitest + @vue/test-utils、Tailwind CSS。

**Spec:** `docs/superpowers/specs/2026-10-10-m6-sidebar-ui-design.md`

## Global Constraints

- ドキュメント・コメントは日本語。
- 新規画面は shadcn-vue を使わず Tailwind で作る（`CLAUDE.md` の UI方針）。
- 既存の ESLint/Prettier 設定に従う（インデント4スペース、シングルクォート）。
- `composer test`（Pint・PHPStan・Pest）と `npm run lint`、`npx vitest run` が通ること。
- `config/tools.php` とツール各画面のファイルは変更しない。管理・認証・設定のレイアウトは変更しない。

## 設計書からの変更点（実装計画で簡略化）

- 検索は「Enter で `/?q=…` に移動」に統一する（入力中のライブ絞り込みはしない）。URL で共有でき、実装が一本化できる。
- モバイルのドロワーは、ヘッダー下の「カテゴリ」ボタンで開閉するパネルにする（オーバーレイにしない）。`PublicHeader` を触らずに済む。
- `CATEGORY_TABS` の名前は変えない（参照が3箇所あり、変更の利益が小さい）。

## Review Focus

- 未知の `?category=xxx`: 「すべて」として扱う（Task 2 のテスト）。
- `?q=` が空白のみ: 絞り込みなし（既存の `filterTools` が trim 済み）。
- ツール一覧にない URL（`/contact`、`/blog/…`）: どのカテゴリも強調しない（Task 2 のテスト）。
- 検索語の特殊文字（`&`、`#`、日本語）: URL エンコードされて往復する（Task 3 のテスト）。
- 未ログインでも `toolNav` が共有される（Task 1 のテスト）。

---

## ファイル構成

| ファイル | 役割 |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php`（変更） | `toolNav` 共有プロパティ |
| `resources/js/types/index.d.ts`（変更） | `PageProps` に `toolNav` を追加 |
| `resources/js/lib/toolFilter.ts`（変更） | `ToolNavItem`、`normalizeCategory`、`countByCategory`、`resolveActiveCategory`、`extractQuery` |
| `resources/js/components/Public/CategorySidebar.vue`（新規） | サイドバー本体 |
| `resources/js/layouts/Public/PublicLayout.vue`（変更） | サイドバーを組み込み、モバイル用の開閉 |
| `resources/js/pages/Home.vue`（変更） | タブ・検索窓を削除し、URL に応じて一覧表示 |
| `tests/Feature/HomeControllerTest.php`（変更） | `toolNav` の確認 |

---

### Task 1: 共有プロパティ `toolNav`

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/types/index.d.ts`
- Test: `tests/Feature/HomeControllerTest.php`

**Interfaces:**
- Produces: すべての Inertia ページの props に `toolNav: Array<{slug: string, name: string, category: string, href: string}>`（説明文は含めない）

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/HomeControllerTest.php` の末尾に追記:

```php
test('toolNav is shared with navigation fields only', function () {
    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('toolNav', count(config('tools')))
        ->where('toolNav.0.slug', 'image-compress')
        ->where('toolNav.0.href', '/image-compress')
        ->missing('toolNav.0.description')
    );
});

test('toolNav is shared on non-tool public pages too', function () {
    $this->get('/contact')->assertInertia(fn ($page) => $page
        ->has('toolNav')
    );
});
```

- [ ] **Step 2: 失敗を確認**

Run: `php artisan test tests/Feature/HomeControllerTest.php`
Expected: FAIL（`toolNav` が存在しない）

- [ ] **Step 3: 実装**

`HandleInertiaRequests::share()` の戻り配列に追加:

```php
            'toolNav' => fn () => collect(config('tools'))
                ->map(fn (array $tool) => [
                    'slug' => $tool['slug'],
                    'name' => $tool['name'],
                    'category' => $tool['category'],
                    'href' => $tool['href'],
                ])
                ->values()
                ->all(),
```

`resources/js/types/index.d.ts` の `PageProps` の型に追加:

```ts
    toolNav: Array<{
        slug: string;
        name: string;
        category: string;
        href: string;
    }>;
```

（`auth` の並びに追記する。）

- [ ] **Step 4: 通過を確認**

Run: `php artisan test tests/Feature/HomeControllerTest.php`
Expected: PASS。`/contact` が存在しない場合は、`routes/web.php` にある公開ページ（例: `/faq`）に差し替える。

- [ ] **Step 5: コミット**

```bash
git add docs/superpowers app/Http/Middleware/HandleInertiaRequests.php resources/js/types/index.d.ts tests/Feature/HomeControllerTest.php
git commit -m "feat: ツールのナビ情報を共有プロパティ toolNav で全ページに渡す(M6)"
```

---

### Task 2: カテゴリ算出ヘルパー

**Files:**
- Modify: `resources/js/lib/toolFilter.ts`
- Test: `resources/js/lib/toolFilter.test.ts`

**Interfaces:**
- Produces（`toolFilter.ts` から export）:
  - `type ToolNavItem = Pick<Tool, 'slug' | 'name' | 'category' | 'href'>`
  - `normalizeCategory(key: string | null | undefined): string` — 未知・空は `'all'`
  - `countByCategory(tools: ToolNavItem[]): Record<string, number>` — `all` を含む
  - `resolveActiveCategory(url: string, tools: ToolNavItem[]): string | null` — `/` は `?category=` を normalize、ツールの href と一致すればそのカテゴリ、それ以外は `null`
  - `extractQuery(url: string): string` — `?q=` の値（なければ空文字）

- [ ] **Step 1: 失敗するテストを書く**

`toolFilter.test.ts` の import を `import { CATEGORY_TABS, countByCategory, extractQuery, filterTools, normalizeCategory, resolveActiveCategory } from './toolFilter';` に変え、末尾に追記:

```ts
describe('normalizeCategory', () => {
    test('returns known category keys as they are', () => {
        expect(normalizeCategory('image')).toBe('image');
    });

    test('falls back to all for unknown, empty or missing values', () => {
        expect(normalizeCategory('nope')).toBe('all');
        expect(normalizeCategory('')).toBe('all');
        expect(normalizeCategory(null)).toBe('all');
        expect(normalizeCategory(undefined)).toBe('all');
    });
});

describe('countByCategory', () => {
    test('counts tools per category and in total', () => {
        expect(countByCategory(tools)).toEqual({
            all: 3,
            image: 1,
            color: 1,
            css: 1,
        });
    });
});

describe('resolveActiveCategory', () => {
    test('uses the category query on the home page', () => {
        expect(resolveActiveCategory('/?category=color', tools)).toBe('color');
        expect(resolveActiveCategory('/', tools)).toBe('all');
    });

    test('treats an unknown category query as all', () => {
        expect(resolveActiveCategory('/?category=xxx', tools)).toBe('all');
    });

    test('uses the category of the tool whose href matches the path', () => {
        expect(resolveActiveCategory('/image-compress', tools)).toBe('image');
        expect(resolveActiveCategory('/css-box-shadow?x=1', tools)).toBe('css');
    });

    test('returns null for pages that are not tools', () => {
        expect(resolveActiveCategory('/contact', tools)).toBeNull();
    });
});

describe('extractQuery', () => {
    test('returns the decoded q parameter', () => {
        expect(extractQuery('/?q=%E7%94%BB%E5%83%8F%26a')).toBe('画像&a');
    });

    test('returns an empty string when q is absent', () => {
        expect(extractQuery('/?category=image')).toBe('');
        expect(extractQuery('/')).toBe('');
    });
});
```

- [ ] **Step 2: 失敗を確認**

Run: `npx vitest run resources/js/lib/toolFilter.test.ts`
Expected: FAIL（export が存在しない）

- [ ] **Step 3: 実装**

`toolFilter.ts` の `CATEGORY_TABS` の下に追記:

```ts
export type ToolNavItem = Pick<Tool, 'slug' | 'name' | 'category' | 'href'>;

export function normalizeCategory(key: string | null | undefined): string {
    return key && CATEGORY_TABS.some((tab) => tab.key === key) ? key : 'all';
}

export function countByCategory(tools: ToolNavItem[]): Record<string, number> {
    const counts: Record<string, number> = { all: tools.length };

    for (const tool of tools) {
        counts[tool.category] = (counts[tool.category] ?? 0) + 1;
    }

    return counts;
}

/**
 * 現在の URL から、サイドバーで強調するカテゴリを求める。
 * トップは ?category=、ツール画面はそのツールのカテゴリ。どちらでもなければ null。
 */
export function resolveActiveCategory(
    url: string,
    tools: ToolNavItem[],
): string | null {
    const [path, search = ''] = url.split('?');

    if (path === '/') {
        return normalizeCategory(new URLSearchParams(search).get('category'));
    }

    return tools.find((tool) => tool.href === path)?.category ?? null;
}

export function extractQuery(url: string): string {
    const search = url.split('?')[1] ?? '';

    return new URLSearchParams(search).get('q') ?? '';
}
```

- [ ] **Step 4: 通過を確認**

Run: `npx vitest run resources/js/lib/toolFilter.test.ts`
Expected: PASS

- [ ] **Step 5: コミット**

```bash
git add resources/js/lib/toolFilter.ts resources/js/lib/toolFilter.test.ts
git commit -m "feat: サイドバー用のカテゴリ算出ヘルパーを追加(M6)"
```

---

### Task 3: `CategorySidebar` コンポーネント

**Files:**
- Create: `resources/js/components/Public/CategorySidebar.vue`
- Test: `resources/js/components/Public/CategorySidebar.test.ts`

**Interfaces:**
- Consumes: Task 2 の `CATEGORY_TABS`、`countByCategory`、`ToolNavItem`
- Produces: `CategorySidebar` — props `{ tools: ToolNavItem[]; activeCategory: string | null; query: string }`。カテゴリ項目は `Link`（`/` または `/?category=<key>`）。検索窓の Enter で `router.get('/', { q })`（空なら `{}`）。

- [ ] **Step 1: 失敗するテストを書く**

`CategorySidebar.test.ts`:

```ts
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, expect, test, vi } from 'vitest';

import CategorySidebar from './CategorySidebar.vue';

const getMock = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { get: (...args: unknown[]) => getMock(...args) },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

const tools = [
    { slug: 'a', name: 'A', category: 'image', href: '/a' },
    { slug: 'b', name: 'B', category: 'image', href: '/b' },
    { slug: 'c', name: 'C', category: 'color', href: '/c' },
];

beforeEach(() => {
    getMock.mockClear();
});

function mountSidebar(props: Partial<{ activeCategory: string | null; query: string }> = {}) {
    return mount(CategorySidebar, {
        props: { tools, activeCategory: 'all', query: '', ...props },
    });
}

test('lists all categories with counts and links', () => {
    const wrapper = mountSidebar();
    const links = wrapper.findAll('a');

    expect(links[0].attributes('href')).toBe('/');
    expect(links[0].text()).toContain('すべて');
    expect(links[0].text()).toContain('3');
    const image = links.find((link) => link.text().includes('画像'));
    expect(image?.attributes('href')).toBe('/?category=image');
    expect(image?.text()).toContain('2');
});

test('marks only the active category with aria-current', () => {
    const wrapper = mountSidebar({ activeCategory: 'color' });
    const current = wrapper.findAll('a[aria-current="page"]');

    expect(current).toHaveLength(1);
    expect(current[0].text()).toContain('色');
});

test('marks nothing as current when activeCategory is null', () => {
    const wrapper = mountSidebar({ activeCategory: null });

    expect(wrapper.findAll('a[aria-current="page"]')).toHaveLength(0);
});

test('submitting the search navigates to the home page with q', async () => {
    const wrapper = mountSidebar();

    await wrapper.find('input[type="search"]').setValue('画像&a #1');
    await wrapper.find('form').trigger('submit');

    expect(getMock).toHaveBeenCalledWith('/', { q: '画像&a #1' });
});

test('an empty search navigates to the home page without q', async () => {
    const wrapper = mountSidebar({ query: '圧縮' });

    await wrapper.find('input[type="search"]').setValue('   ');
    await wrapper.find('form').trigger('submit');

    expect(getMock).toHaveBeenCalledWith('/', {});
});

test('shows the current query in the search box', () => {
    const wrapper = mountSidebar({ query: '圧縮' });

    expect(
        (wrapper.find('input[type="search"]').element as HTMLInputElement).value,
    ).toBe('圧縮');
});
```

- [ ] **Step 2: 失敗を確認**

Run: `npx vitest run resources/js/components/Public/CategorySidebar.test.ts`
Expected: FAIL（コンポーネントが存在しない）

- [ ] **Step 3: 実装**

`CategorySidebar.vue`:

```vue
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { CATEGORY_TABS, countByCategory } from '@/lib/toolFilter';
import type { ToolNavItem } from '@/lib/toolFilter';

const props = defineProps<{
    tools: ToolNavItem[];
    activeCategory: string | null;
    query: string;
}>();

const keyword = ref(props.query);

watch(
    () => props.query,
    (value) => {
        keyword.value = value;
    },
);

const counts = computed(() => countByCategory(props.tools));

function hrefFor(key: string): string {
    return key === 'all' ? '/' : `/?category=${key}`;
}

function submit(): void {
    const q = keyword.value.trim();

    router.get('/', q === '' ? {} : { q });
}
</script>

<template>
    <aside class="w-full lg:w-60 lg:shrink-0">
        <form class="mb-4" @submit.prevent="submit">
            <input
                v-model="keyword"
                type="search"
                placeholder="ツールを検索..."
                aria-label="ツールを検索"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
            />
        </form>

        <nav aria-label="カテゴリ">
            <ul class="space-y-1">
                <li v-for="tab in CATEGORY_TABS" :key="tab.key">
                    <Link
                        :href="hrefFor(tab.key)"
                        :aria-current="
                            activeCategory === tab.key ? 'page' : undefined
                        "
                        class="flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="
                            activeCategory === tab.key
                                ? 'bg-gray-900 text-white'
                                : 'text-gray-600 hover:bg-gray-100'
                        "
                    >
                        <span>{{ tab.label }}</span>
                        <span
                            class="text-xs"
                            :class="
                                activeCategory === tab.key
                                    ? 'text-gray-300'
                                    : 'text-gray-400'
                            "
                            >{{ counts[tab.key] ?? 0 }}</span
                        >
                    </Link>
                </li>
            </ul>
        </nav>
    </aside>
</template>
```

- [ ] **Step 4: 通過を確認**

Run: `npx vitest run resources/js/components/Public/CategorySidebar.test.ts`
Expected: PASS

- [ ] **Step 5: コミット**

```bash
git add resources/js/components/Public/CategorySidebar.vue resources/js/components/Public/CategorySidebar.test.ts
git commit -m "feat: カテゴリサイドバーのコンポーネントを追加(M6)"
```

---

### Task 4: レイアウトへの組み込みとトップの改修

**Files:**
- Modify: `resources/js/layouts/Public/PublicLayout.vue`
- Modify: `resources/js/pages/Home.vue`
- Test: ブラウザ確認（Playwright）。コンポーネントの単体テストは Task 3 で済み。

**Interfaces:**
- Consumes: Task 1 の `page.props.toolNav`、Task 2 の `resolveActiveCategory`・`extractQuery`、Task 3 の `CategorySidebar`

- [ ] **Step 1: `PublicLayout.vue` を置き換える**

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CategorySidebar from '@/components/Public/CategorySidebar.vue';
import PublicFooter from '@/components/Public/PublicFooter.vue';
import PublicHeader from '@/components/Public/PublicHeader.vue';
import { extractQuery, resolveActiveCategory } from '@/lib/toolFilter';

const page = usePage();
const user = computed(() => page.props.auth.user);
const toolNav = computed(() => page.props.toolNav);
const activeCategory = computed(() =>
    resolveActiveCategory(page.url, toolNav.value),
);
const query = computed(() => extractQuery(page.url));

const showingSidebar = ref(false);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <PublicHeader :user="user" />

        <div class="mx-auto max-w-7xl px-6 py-6 lg:flex lg:gap-8">
            <button
                type="button"
                class="mb-4 inline-flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 lg:hidden"
                :aria-expanded="showingSidebar"
                @click="showingSidebar = !showingSidebar"
            >
                カテゴリ・検索
                <span aria-hidden="true">{{ showingSidebar ? '−' : '+' }}</span>
            </button>

            <div
                :class="showingSidebar ? 'block' : 'hidden'"
                class="mb-6 lg:mb-0 lg:block"
            >
                <CategorySidebar
                    :tools="toolNav"
                    :active-category="activeCategory"
                    :query="query"
                />
            </div>

            <main class="min-w-0 flex-1">
                <slot />
            </main>
        </div>

        <PublicFooter />
    </div>
</template>
```

- [ ] **Step 2: `Home.vue` を置き換える**

メインは外側の `max-w-7xl` がレイアウトに移るため、Home 側の余白枠を外す。

```vue
<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import PremiumBanner from '@/components/PremiumBanner.vue';
import ToolCard from '@/components/tools/ToolCard.vue';
import WebsiteStarterBanner from '@/components/WebsiteStarterBanner.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import {
    CATEGORY_TABS,
    extractQuery,
    filterTools,
    normalizeCategory,
} from '@/lib/toolFilter';
import type { Tool } from '@/lib/toolFilter';

const props = defineProps<{ tools: Tool[] }>();

const page = usePage();

const activeCategory = computed(() =>
    normalizeCategory(
        new URLSearchParams(page.url.split('?')[1] ?? '').get('category'),
    ),
);
const query = computed(() => extractQuery(page.url));

const heading = computed(
    () =>
        CATEGORY_TABS.find((tab) => tab.key === activeCategory.value)?.label ??
        'すべて',
);

const filteredTools = computed(() =>
    filterTools(props.tools, activeCategory.value, query.value),
);
</script>

<template>
    <Head title="Web制作ツール集 - katsuooool" />

    <PublicLayout>
        <h1 class="text-2xl font-bold text-gray-900">
            {{ heading }}
            <span class="ml-2 text-sm font-normal text-gray-500"
                >{{ filteredTools.length }}件</span
            >
        </h1>
        <p v-if="query !== ''" class="mt-1 text-sm text-gray-500">
            「{{ query }}」の検索結果
        </p>

        <div
            v-if="filteredTools.length === 0"
            class="mt-10 text-sm text-gray-500"
        >
            該当するツールが見つかりませんでした。
        </div>

        <div
            v-else
            class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <ToolCard
                v-for="tool in filteredTools"
                :key="tool.slug"
                :tool="tool"
            />
        </div>

        <PremiumBanner />
        <WebsiteStarterBanner />
    </PublicLayout>
</template>
```

- [ ] **Step 3: 自動テスト・Lint を通す**

Run: `npx vitest run && npm run lint && composer test`
Expected: すべて PASS。既存の画面テスト（`ImageRotate.test.ts` など）が `PublicLayout` を通る場合に落ちたら、そのテストの `usePage` モックに `toolNav: []`・`url: '/'` を足す。

- [ ] **Step 4: ブラウザで確認**

Run: `composer dev`（別ターミナル）。Playwright で次を確認し、スクリーンショットを `scratchpad` に保存する。
1. デスクトップ幅（1280px）: `/` にサイドバー、`/?category=image` で画像6件、`/image-compress` でサイドバーの「画像」が強調される。
2. モバイル幅（390px）: 「カテゴリ・検索」ボタンで開閉できる。
3. 検索: 「色」と入力して Enter → `/?q=色`。結果と見出しの表示が出る。
4. `/contact`: サイドバーは出て、どのカテゴリも強調されない。
5. `/?category=xxx`: 「すべて」と同じ表示。

- [ ] **Step 5: コミット**

```bash
git add resources/js/layouts/Public/PublicLayout.vue resources/js/pages/Home.vue
git commit -m "feat: 公開ページに左サイドバーを組み込み、トップをカテゴリ別一覧にする(M6)"
```

---

### Task 5: 仕様書・ロードマップの更新

**Files:**
- Modify: `SPEC.md`（トップページの記述 §2〜3 付近の「トップページ: 検索窓 + カテゴリ別カード一覧」）
- Modify: `docs/superpowers/specs/2026-10-10-m6-sidebar-ui-design.md`（§4.2・§4.1・§4.5 を実装に合わせて修正）
- Modify: `/Users/katsuhiro.k1215/SmartSprouts/company/rooms/katsuooool/dev/roadmap/aws-migration-and-ui.md`（M6 の結果メモ）

- [ ] **Step 1: `SPEC.md` のトップページの行を差し替える**

`- トップページ: 検索窓 + カテゴリ別カード一覧…` を次に置き換える:

`- 公開ページ共通: 左サイドバー（検索窓・カテゴリ一覧と件数）+ メイン。トップは `?category=` と `?q=` に応じたカード一覧。モバイルは「カテゴリ・検索」ボタンで開閉する。`

- [ ] **Step 2: 設計書を実装に合わせる**

§4.2 の検索の説明を「Enter で `/?q=…` に移動（ライブ絞り込みはしない）」に、§4.1 のモバイルを「開閉パネル」に、§4.5 を「`CATEGORY_TABS` の名前は変えない」に直す。

- [ ] **Step 3: ロードマップに結果メモを追記し、M6 を in-progress（本番反映前）にする**

`M6 の結果メモ（2026-10-10）` として、変更の要点・検証結果・未デプロイであることを追記する。

- [ ] **Step 4: コミット**

```bash
git add SPEC.md docs/superpowers/specs/2026-10-10-m6-sidebar-ui-design.md
git commit -m "docs: M6 の仕様書を実装に合わせて更新"
```

（ロードマップは `company/` 側のためコミット対象外。）

---

## 本番反映

この計画には含めない。ローカル確認後、オーナーの確認を取って別手順で行う（`up -d --force-recreate app scheduler`、`katsuooool-web` は触らない）。
