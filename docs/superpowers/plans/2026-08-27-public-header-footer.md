# 公開ページ共通ヘッダー/フッター Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 公開ページ共通の`PublicHeader`/`PublicFooter`/`PublicLayout`を実装し、Home.vueに適用する。あわせて削除されたまま放置されていた`GuestLayout.vue`を復旧し、ログイン画面のビルドエラーを修正する。

**Architecture:** `PublicHeader.vue`はログイン状態(`user`プロパティ、`null`または`{name: string}`)を受け取り表示を切り替える。ログイン状態の取得(`usePage()`)は`PublicLayout.vue`が担い、`PublicHeader`へpropsとして渡すことで、`PublicHeader`自体をInertiaのグローバル状態に依存しない純粋なコンポーネントとしてテスト可能にする。`PublicFooter`は静的な4カラムFooterNav。`Home.vue`は独自ヘッダーを廃し`PublicLayout`でラップする。

**Tech Stack:** PHP 8.3 / Laravel 13 / Inertia.js v2 / Vue 3 / TypeScript / Vitest + @vue/test-utils

**Spec:** docs/superpowers/specs/2026-08-27-public-header-footer-design.md

## Global Constraints

- ヘッダー右側の会社情報ドロップダウンの外部リンクは`target="_blank" rel="noopener noreferrer"`を必ず付与する。
- 外部リンクURL: かつコード `https://katsucode.jp/`、katsulog `https://katsu-coach.com/`、SmartSprouts `https://smartsprouts.jp/`。
- 「Spra」という名称のリンクは今回追加しない(将来別途追加)。
- ロゴサイズは`h-8`(ヘッダー内)・`h-12`(GuestLayout内)を基準とする。
- ダークモード(`dark:`)対応は行わない(既存の他ページに合わせ通常表示のみ)。
- 既存の`AppFooter.vue`は削除しない(他11ページが引き続き使用するため、置き換えは別プロジェクト)。
- 各タスク完了時に`composer ci:check`(ESLint/Prettier/vue-tsc/Vitest/Pint/PHPStan/Pest)がグリーンであること。

---

### Task 1: `ApplicationLogo.vue`置き換え + `GuestLayout.vue`復旧

**Files:**
- Modify: `resources/js/components/ApplicationLogo.vue`
- Create: `resources/js/layouts/GuestLayout.vue`(復旧・新規作成)
- Test: `resources/js/components/ApplicationLogo.test.ts`
- Test: `resources/js/layouts/GuestLayout.test.ts`

**Interfaces:**
- Produces: `ApplicationLogo.vue`(`<img src="/upload/logo.svg">`、propなし、`class`は呼び出し側から指定)、`GuestLayout.vue`(デフォルトスロットを受け取るレイアウト)

- [ ] **Step 1: 失敗するテストを書く(ApplicationLogo)**

`resources/js/components/ApplicationLogo.test.ts`を新規作成:

```typescript
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import ApplicationLogo from './ApplicationLogo.vue';

test('renders the logo image with the correct source and alt text', () => {
    const wrapper = mount(ApplicationLogo);

    const img = wrapper.find('img');
    expect(img.attributes('src')).toBe('/upload/logo.svg');
    expect(img.attributes('alt')).toBe('katsuooool');
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `npm run test -- ApplicationLogo`
Expected: FAIL(現在の`ApplicationLogo.vue`はインラインSVGで`<img>`タグが存在しない)

- [ ] **Step 3: `ApplicationLogo.vue`を実装する**

`resources/js/components/ApplicationLogo.vue`を以下に置き換える:

```vue
<template>
    <img src="/upload/logo.svg" alt="katsuooool" class="h-8 w-auto" />
</template>
```

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `npm run test -- ApplicationLogo`
Expected: 1 test PASS

- [ ] **Step 5: 失敗するテストを書く(GuestLayout)**

`resources/js/layouts/GuestLayout.test.ts`を新規作成:

```typescript
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import GuestLayout from './GuestLayout.vue';

test('renders the logo and the default slot content', () => {
    const wrapper = mount(GuestLayout, {
        slots: {
            default: '<p>ログインフォーム</p>',
        },
        global: {
            stubs: {
                Link: { template: '<a><slot /></a>' },
            },
        },
    });

    expect(wrapper.find('img').attributes('src')).toBe('/upload/logo.svg');
    expect(wrapper.text()).toContain('ログインフォーム');
});
```

- [ ] **Step 6: テストを実行して失敗を確認する**

Run: `npm run test -- GuestLayout`
Expected: FAIL(`resources/js/layouts/GuestLayout.vue`が存在しない)

- [ ] **Step 7: `GuestLayout.vue`を作成する**

`resources/js/layouts/GuestLayout.vue`(新規):

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/components/ApplicationLogo.vue';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-12 w-auto" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
```

- [ ] **Step 8: テストを実行して通ることを確認する**

Run: `npm run test -- GuestLayout`
Expected: 1 test PASS

- [ ] **Step 9: 6つの認証ページが正しくビルドされることを確認する**

Run: `npx vue-tsc --noEmit`
Expected: `auth/Login.vue`・`Register.vue`・`ForgotPassword.vue`・`ResetPassword.vue`・`ConfirmPassword.vue`・`VerifyEmail.vue`関連のエラーが解消されていること(それ以外のエラーが出ないこと)

- [ ] **Step 10: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 11: コミットする**

```bash
git add resources/js/components/ApplicationLogo.vue resources/js/components/ApplicationLogo.test.ts resources/js/layouts/GuestLayout.vue resources/js/layouts/GuestLayout.test.ts
git commit -m "fix: restore deleted GuestLayout.vue and replace logo with the new SVG"
```

---

### Task 2: `PublicHeader.vue`実装

**Files:**
- Modify: `resources/js/components/Public/PublicHeader.vue`
- Test: `resources/js/components/Public/PublicHeader.test.ts`

**Interfaces:**
- Consumes: `App/Dropdown.vue`(既存)、`App/DropdownLink.vue`(既存)、`ApplicationLogo.vue`(Task 1)
- Produces: `PublicHeader`のprops `{ user: { name: string } | null }`(Task 4の`PublicLayout.vue`が`usePage()`から取得して渡す)

- [ ] **Step 1: 失敗するテストを書く**

`resources/js/components/Public/PublicHeader.test.ts`を新規作成:

```typescript
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { beforeEach, expect, test, vi } from 'vitest';

import PublicHeader from './PublicHeader.vue';

const linkStub = { template: '<a><slot /></a>', props: ['href'] };

// PublicHeader.vue calls the global route() helper (registered at runtime
// by Ziggy's Vue plugin, resources/js/app.ts). Vitest's jsdom environment
// never installs that plugin, so route() must be stubbed or the component
// throws "route is not defined" on mount, failing every test in this file.
beforeEach(() => {
    vi.stubGlobal('route', (name: string) => {
        const routes: Record<string, string> = {
            login: '/login',
            register: '/register',
            'mypage.index': '/mypage',
            logout: '/logout',
        };

        return routes[name] ?? `/${name}`;
    });
});

test('shows login and register links when the user is not logged in', () => {
    const wrapper = mount(PublicHeader, {
        props: { user: null },
        global: { stubs: { Link: linkStub } },
    });

    expect(wrapper.text()).toContain('ログイン');
    expect(wrapper.text()).toContain('新規登録');
    expect(wrapper.text()).not.toContain('マイページ');
});

test('shows the user menu instead of login/register links when logged in', () => {
    const wrapper = mount(PublicHeader, {
        props: { user: { name: '山田太郎' } },
        global: { stubs: { Link: linkStub } },
    });

    expect(wrapper.text()).toContain('山田太郎');
    expect(wrapper.text()).not.toContain('新規登録');
});

test('the company info dropdown links open in a new tab safely', () => {
    const wrapper = mount(PublicHeader, {
        props: { user: null },
        global: { stubs: { Link: linkStub } },
    });

    const katsucode = wrapper.find('a[href="https://katsucode.jp/"]');
    expect(katsucode.attributes('target')).toBe('_blank');
    expect(katsucode.attributes('rel')).toBe('noopener noreferrer');

    expect(wrapper.find('a[href="https://katsu-coach.com/"]').exists()).toBe(
        true,
    );
    expect(
        wrapper.find('a[href="https://smartsprouts.jp/"]').exists(),
    ).toBe(true);
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `npm run test -- PublicHeader`
Expected: FAIL(`PublicHeader.vue`は現在空のテンプレート)

- [ ] **Step 3: `PublicHeader.vue`を実装する**

`resources/js/components/Public/PublicHeader.vue`を以下に置き換える:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import Dropdown from '@/components/Dropdown.vue';
import DropdownLink from '@/components/DropdownLink.vue';

defineProps<{
    user: { name: string } | null;
}>();
</script>

<template>
    <header class="border-b border-gray-200 bg-white">
        <div
            class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4"
        >
            <Link href="/" class="flex items-center gap-3">
                <ApplicationLogo class="h-8 w-auto" />
                <span class="text-lg font-bold text-gray-900">katsuooool</span>
                <span class="hidden text-sm text-gray-500 sm:inline"
                    >Web制作に役立つ実用ツール集</span
                >
            </Link>

            <div class="flex items-center gap-4">
                <Link
                    href="/premium"
                    class="text-sm font-medium text-gray-500 hover:text-gray-700"
                >
                    Premium
                </Link>

                <template v-if="user">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700"
                            >
                                {{ user.name }}
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('mypage.index')">
                                マイページ
                            </DropdownLink>
                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                ログアウト
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </template>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                        ログイン
                    </Link>
                    <Link
                        :href="route('register')"
                        class="text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                        新規登録
                    </Link>
                </template>

                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700"
                        >
                            その他
                            <svg
                                class="h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>
                    </template>
                    <template #content>
                        <a
                            href="https://katsucode.jp/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                        >
                            かつコード
                        </a>
                        <a
                            href="https://katsu-coach.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                        >
                            katsulog
                        </a>
                        <a
                            href="https://smartsprouts.jp/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                        >
                            SmartSprouts
                        </a>
                        <DropdownLink href="/contact"> お問い合わせ </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </div>
    </header>
</template>
```

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `npm run test -- PublicHeader`
Expected: 3 tests PASS

- [ ] **Step 5: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 6: コミットする**

```bash
git add resources/js/components/Public/PublicHeader.vue resources/js/components/Public/PublicHeader.test.ts
git commit -m "feat: implement PublicHeader with auth-aware navigation"
```

---

### Task 3: `PublicFooter.vue`実装

**Files:**
- Modify: `resources/js/components/Public/PublicFooter.vue`
- Test: `resources/js/components/Public/PublicFooter.test.ts`

**Interfaces:**
- Produces: `PublicFooter.vue`(propなし、静的な4カラムFooterNav)

- [ ] **Step 1: 失敗するテストを書く**

`resources/js/components/Public/PublicFooter.test.ts`を新規作成:

```typescript
// @vitest-environment jsdom
import { mount } from '@vue/test-utils';
import { expect, test } from 'vitest';

import PublicFooter from './PublicFooter.vue';

test('renders all four navigation groups with the correct links', () => {
    const wrapper = mount(PublicFooter);

    const expectedHrefs = [
        '/about',
        '/service',
        '/premium',
        '/faq',
        'https://katsucode.jp/',
        'https://katsu-coach.com/',
        'https://smartsprouts.jp/',
        '/legal/privacy-policy',
        '/legal/terms',
        '/legal/cookie-policy',
        '/contact',
        '/blog',
    ];

    for (const href of expectedHrefs) {
        expect(wrapper.find(`a[href="${href}"]`).exists()).toBe(true);
    }
});

test('external links open in a new tab safely', () => {
    const wrapper = mount(PublicFooter);

    const external = wrapper.find('a[href="https://katsucode.jp/"]');
    expect(external.attributes('target')).toBe('_blank');
    expect(external.attributes('rel')).toBe('noopener noreferrer');
});

test('shows the current year in the copyright line', () => {
    const wrapper = mount(PublicFooter);

    expect(wrapper.text()).toContain(`${new Date().getFullYear()}`);
    expect(wrapper.text()).toContain('katsuooool');
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `npm run test -- PublicFooter`
Expected: FAIL(`PublicFooter.vue`は現在ダミーの単一行フッター)

- [ ] **Step 3: `PublicFooter.vue`を実装する**

`resources/js/components/Public/PublicFooter.vue`を以下に置き換える:

```vue
<script setup lang="ts">
import { computed } from 'vue';

const currentYear = computed(() => new Date().getFullYear());

const NAV_GROUPS = [
    {
        heading: 'ツール集',
        links: [
            { label: 'About', href: '/about' },
            { label: 'Service', href: '/service' },
            { label: '料金案内', href: '/premium' },
            { label: 'よくある質問', href: '/faq' },
        ],
    },
    {
        heading: '関連サービス',
        links: [
            {
                label: 'かつコード',
                href: 'https://katsucode.jp/',
                external: true,
            },
            {
                label: 'katsulog',
                href: 'https://katsu-coach.com/',
                external: true,
            },
            {
                label: 'SmartSprouts',
                href: 'https://smartsprouts.jp/',
                external: true,
            },
        ],
    },
    {
        heading: '規約・ポリシー',
        links: [
            { label: 'プライバシーポリシー', href: '/legal/privacy-policy' },
            { label: '利用規約', href: '/legal/terms' },
            { label: 'クッキーポリシー', href: '/legal/cookie-policy' },
        ],
    },
    {
        heading: '会社情報',
        links: [
            { label: '会社案内', href: '/about' },
            { label: 'お問い合わせ', href: '/contact' },
            { label: 'ブログ・お知らせ', href: '/blog' },
        ],
    },
];
</script>

<template>
    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-10">
            <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                <div v-for="group in NAV_GROUPS" :key="group.heading">
                    <p
                        class="text-xs font-semibold tracking-wide text-gray-400 uppercase"
                    >
                        {{ group.heading }}
                    </p>
                    <nav class="mt-3 flex flex-col gap-2">
                        <a
                            v-for="link in group.links"
                            :key="link.href"
                            :href="link.href"
                            :target="link.external ? '_blank' : undefined"
                            :rel="
                                link.external
                                    ? 'noopener noreferrer'
                                    : undefined
                            "
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            {{ link.label }}
                        </a>
                    </nav>
                </div>
            </div>

            <p class="mt-10 text-sm text-gray-400">
                &copy; {{ currentYear }} katsuooool
            </p>
        </div>
    </footer>
</template>
```

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `npm run test -- PublicFooter`
Expected: 3 tests PASS

- [ ] **Step 5: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 6: コミットする**

```bash
git add resources/js/components/Public/PublicFooter.vue resources/js/components/Public/PublicFooter.test.ts
git commit -m "feat: implement PublicFooter with four navigation groups"
```

---

### Task 4: `PublicLayout.vue`配線 + `PremiumBanner.vue` + `Home.vue`再構成

**Files:**
- Modify: `resources/js/layouts/Public/PublicLayout.vue`
- Create: `resources/js/components/PremiumBanner.vue`
- Modify: `resources/js/pages/Home.vue`

**Interfaces:**
- Consumes: `PublicHeader`(Task 2、`user` prop)、`PublicFooter`(Task 3)

- [ ] **Step 1: `PublicLayout.vue`を実装する**

`resources/js/layouts/Public/PublicLayout.vue`を以下に置き換える:

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicFooter from '@/components/Public/PublicFooter.vue';
import PublicHeader from '@/components/Public/PublicHeader.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <PublicHeader :user="user" />

        <main>
            <slot />
        </main>

        <PublicFooter />
    </div>
</template>
```

- [ ] **Step 2: `PremiumBanner.vue`を作成する**

`resources/js/components/PremiumBanner.vue`(新規):

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="mt-16 rounded-lg bg-gray-900 px-6 py-8 text-center text-white sm:px-12"
    >
        <p class="text-lg font-bold">もっと便利に使いたい方へ</p>
        <p class="mt-2 text-sm text-gray-300">
            Premiumならバッチ処理の上限アップ・利用履歴の閲覧ができます。
        </p>
        <Link
            href="/premium"
            class="mt-4 inline-block rounded-md bg-white px-6 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100"
        >
            Premiumを見る
        </Link>
    </div>
</template>
```

- [ ] **Step 3: `Home.vue`を再構成する**

`resources/js/pages/Home.vue`を以下に置き換える:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PremiumBanner from '@/components/PremiumBanner.vue';
import ToolCard from '@/components/tools/ToolCard.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { CATEGORY_TABS, filterTools } from '@/lib/toolFilter';
import type { Tool } from '@/lib/toolFilter';

const props = defineProps<{ tools: Tool[] }>();

const query = ref('');
const activeCategory = ref('all');

const filteredTools = computed(() =>
    filterTools(props.tools, activeCategory.value, query.value),
);
</script>

<template>
    <Head title="katsuooool - Web制作ツール集" />

    <PublicLayout>
        <div class="mx-auto max-w-7xl px-6 py-10">
            <input
                v-model="query"
                type="search"
                placeholder="ツールを検索..."
                class="w-full max-w-md rounded-md border border-gray-300 px-4 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
            />

            <div class="-mx-6 mt-4 overflow-x-auto px-6">
                <div class="flex gap-2">
                    <button
                        v-for="tab in CATEGORY_TABS"
                        :key="tab.key"
                        type="button"
                        class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition"
                        :class="
                            activeCategory === tab.key
                                ? 'border-gray-900 bg-gray-900 text-white'
                                : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400'
                        "
                        @click="activeCategory = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div
                v-if="filteredTools.length === 0"
                class="mt-10 text-sm text-gray-500"
            >
                該当するツールが見つかりませんでした。
            </div>

            <div
                v-else
                class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <ToolCard
                    v-for="tool in filteredTools"
                    :key="tool.slug"
                    :tool="tool"
                />
            </div>

            <PremiumBanner />
        </div>
    </PublicLayout>
</template>
```

- [ ] **Step 4: 既存のバックエンドFeatureテストが壊れていないことを確認する**

Run: `php artisan test tests/Feature/HomeControllerTest.php`
Expected: 1 test PASS(`Home`コンポーネント・`tools`propの形は変わっていないため、既存テストはそのまま通る)

- [ ] **Step 5: 型チェック・コンポーネントテストを実行する**

Run: `npx vue-tsc --noEmit`
Expected: エラーなし

Run: `npm run test -- PublicHeader PublicFooter ApplicationLogo GuestLayout`
Expected: 全てPASS(Task 1〜3で作成したテストの回帰確認)

- [ ] **Step 6: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 7: コミットする**

```bash
git add resources/js/layouts/Public/PublicLayout.vue resources/js/components/PremiumBanner.vue resources/js/pages/Home.vue
git commit -m "feat: wire PublicLayout into Home.vue with a Premium banner"
```

---

### Task 5: 最終確認・ドキュメント更新

**Files:**
- Modify: `TASKS.md`
- Modify: `SPEC.md`

**Interfaces:**
- Consumes: 全タスクの完了

- [ ] **Step 1: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 2: Playwrightで実ブラウザから一連の動作を確認する**

Playwright MCPツールを使って手動で確認する:

1. 未ログイン状態で`/`にアクセスし、ヘッダー左側にロゴ+「katsuooool」+キャッチコピー、右側に「Premium」「ログイン」「新規登録」「その他」ドロップダウンが表示されることを確認する。
2. 「その他」ドロップダウンを開き、かつコード・katsulog・SmartSprouts・お問い合わせのリンクが表示され、外部リンクが新しいタブで開くこと(`target="_blank"`)を確認する。
3. ログイン(既存のUserアカウントで、なければ`php artisan tinker`で作成)し、`/`にアクセスして、ヘッダー右側が「ログイン」「新規登録」からユーザー名のドロップダウンに切り替わっていること、ドロップダウンから「マイページ」「ログアウト」が機能することを確認する。
4. ツール一覧の下にPremiumバナーが表示され、「Premiumを見る」リンクが`/premium`に遷移することを確認する。
5. フッターの4つのNavグループと全リンクが正しく表示・遷移することを確認する。
6. `/login`にアクセスし、GuestLayout復旧後のログイン画面が崩れていないこと(ロゴ・フォームが正しく表示される)を確認する。

- [ ] **Step 3: `TASKS.md`にPhase 28として実装内容を追記する**

`TASKS.md`の「今後の実装順序」セクションの直前に、Phase 28のセクションを追加し、`PublicHeader`/`PublicFooter`/`PublicLayout`の実装・`GuestLayout.vue`の復旧・Home.vueの再構成を`- [x]`のチェックリストとして記録する。また、残り約11ページへの`PublicLayout`展開が別プロジェクト(サブプロジェクトB)として残っていることを明記する。

- [ ] **Step 4: `SPEC.md`の該当箇所を更新する**

新しいセクション番号で、公開ページ共通ヘッダー/フッターの仕様概要を追記する(詳細は本Planと`docs/superpowers/specs/2026-08-27-public-header-footer-design.md`を参照する形でよい)。

- [ ] **Step 5: コミットする**

```bash
git add TASKS.md SPEC.md
git commit -m "docs: record public header/footer implementation in TASKS.md and SPEC.md"
```

- [ ] **Step 6: リモートにpushする**

```bash
git push
```
