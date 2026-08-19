# CSSカテゴリ(Phase 10)Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** CSSカテゴリの5ツール(Box Shadow・Border Radius・Glassmorphism・Flexbox・Grid)を実装する。いずれも入力値を調整するとリアルタイムでCSSコードが生成される、ブラウザ内完結型のツール。

**Architecture:** 既存のColorカテゴリ(Phase 6)・SEOカテゴリのtitle/descriptionチェック(Phase 9)と同じ「`TrackedToolController`継承 + クライアント完結」パターン。CSS文字列生成ロジックは`resources/js/lib/cssGenerators.ts`に純粋関数として集約し、Vitestで単体テストする。

**Tech Stack:** PHP 8.3 / Laravel 13 / Vue 3 + TypeScript / Vitest / Pest 4

**Spec:** [docs/superpowers/specs/2026-08-20-css-category-design.md](../specs/2026-08-20-css-category-design.md)

## Global Constraints

- ドキュメント成果物・コード内の文言はすべて日本語(CLAUDE.md)。
- 新規ツール画面はshadcn-vueを使わず、Tailwind CSSで独自に組み立てる(CLAUDE.md UI方針)。
- 各ツールはファイルアップロードを伴わない(SPEC.md該当セクション、既存Color/SEOテキストツールと同型)。
- `PHPStan level 7`(`composer.json`の`types:check`)、ESLint/Prettier/vue-tsc(`npm run lint:check`/`format:check`/`types:check`)がすべてグリーンであること。
- 各ツールの`track`エンドポイントには`throttle:30,1`を適用(既存Color/SEOテキストツールと同じ値)。

---

## 参考: 既存パターン

- `App\Http\Controllers\Tools\Concerns\TrackedToolController`(`app/Http/Controllers/Tools/Concerns/TrackedToolController.php`): `show()`でInertiaページ表示、`track()`で`ToolEventLogger::log(eventType: 'process')`のみ記録。
- `config/tools.php`: `slug`/`name`/`category`/`description`/`href`の配列。
- `routes/web.php`: 各ツールの`GET`(表示)+`POST /track`(ログ)ルートを追記。
- `tests/Feature/Admin/DashboardControllerTest.php:46`: `->has('stats', 21)` — ツール数が増えるたびにこの数値を更新する(5ツール追加後は26)。
- コピー機能の参考実装: `resources/js/pages/ColorPalette.vue`の`copy()`関数(`navigator.clipboard.writeText()` + 1.5秒後にリセット)。

---

### Task 1: 共通基盤(`cssGenerators.ts`)

**Files:**
- Create: `resources/js/lib/cssGenerators.ts`
- Create: `resources/js/lib/cssGenerators.test.ts`

**Interfaces:**
- Consumes: なし(このタスクが基盤)。
- Produces:
  - `export interface BoxShadowInput { x: number; y: number; blur: number; spread: number; color: string; inset: boolean }`
  - `export function buildBoxShadow(input: BoxShadowInput): string`
  - `export interface BorderRadiusInput { topLeft: number; topRight: number; bottomRight: number; bottomLeft: number }`
  - `export function buildBorderRadius(input: BorderRadiusInput): string`
  - `export interface GlassmorphismInput { opacity: number; blur: number; borderOpacity: number; shadowOpacity: number }`
  - `export function buildGlassmorphism(input: GlassmorphismInput): string`
  - `export type FlexDirection = 'row' | 'row-reverse' | 'column' | 'column-reverse'`
  - `export type JustifyContent = 'flex-start' | 'flex-end' | 'center' | 'space-between' | 'space-around' | 'space-evenly'`
  - `export type AlignItems = 'stretch' | 'flex-start' | 'flex-end' | 'center' | 'baseline'`
  - `export type FlexWrap = 'nowrap' | 'wrap' | 'wrap-reverse'`
  - `export interface FlexboxInput { direction: FlexDirection; justifyContent: JustifyContent; alignItems: AlignItems; wrap: FlexWrap; gap: number }`
  - `export function buildFlexbox(input: FlexboxInput): string`
  - `export interface GridInput { columns: number; rows: number; gap: number }`
  - `export function buildGrid(input: GridInput): string`

- [ ] **Step 1: 失敗するテストを書く**

```typescript
import { describe, expect, test } from 'vitest';

import {
    buildBoxShadow,
    buildBorderRadius,
    buildFlexbox,
    buildGlassmorphism,
    buildGrid,
} from './cssGenerators';

describe('buildBoxShadow', () => {
    test('builds a standard box-shadow declaration', () => {
        const result = buildBoxShadow({
            x: 4,
            y: 8,
            blur: 16,
            spread: 0,
            color: '#000000',
            inset: false,
        });

        expect(result).toBe('box-shadow: 4px 8px 16px 0px #000000;');
    });

    test('prefixes inset when inset is true', () => {
        const result = buildBoxShadow({
            x: 0,
            y: 0,
            blur: 10,
            spread: -2,
            color: '#ff0000',
            inset: true,
        });

        expect(result).toBe('box-shadow: inset 0px 0px 10px -2px #ff0000;');
    });
});

describe('buildBorderRadius', () => {
    test('outputs all four corners in top-left, top-right, bottom-right, bottom-left order', () => {
        const result = buildBorderRadius({
            topLeft: 4,
            topRight: 8,
            bottomRight: 12,
            bottomLeft: 16,
        });

        expect(result).toBe('border-radius: 4px 8px 12px 16px;');
    });

    test('does not collapse equal corners into a single value', () => {
        const result = buildBorderRadius({
            topLeft: 10,
            topRight: 10,
            bottomRight: 10,
            bottomLeft: 10,
        });

        expect(result).toBe('border-radius: 10px 10px 10px 10px;');
    });
});

describe('buildGlassmorphism', () => {
    test('builds the four-line glassmorphism CSS block', () => {
        const result = buildGlassmorphism({
            opacity: 0.2,
            blur: 10,
            borderOpacity: 0.3,
            shadowOpacity: 0.37,
        });

        expect(result).toBe(
            'background: rgba(255, 255, 255, 0.2);\n' +
                'backdrop-filter: blur(10px);\n' +
                'border: 1px solid rgba(255, 255, 255, 0.3);\n' +
                'box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);',
        );
    });
});

describe('buildFlexbox', () => {
    test('builds the six-line flexbox CSS block including defaults', () => {
        const result = buildFlexbox({
            direction: 'row',
            justifyContent: 'flex-start',
            alignItems: 'stretch',
            wrap: 'nowrap',
            gap: 0,
        });

        expect(result).toBe(
            'display: flex;\n' +
                'flex-direction: row;\n' +
                'justify-content: flex-start;\n' +
                'align-items: stretch;\n' +
                'flex-wrap: nowrap;\n' +
                'gap: 0px;',
        );
    });

    test('reflects non-default values', () => {
        const result = buildFlexbox({
            direction: 'column',
            justifyContent: 'space-between',
            alignItems: 'center',
            wrap: 'wrap',
            gap: 16,
        });

        expect(result).toContain('flex-direction: column;');
        expect(result).toContain('justify-content: space-between;');
        expect(result).toContain('gap: 16px;');
    });
});

describe('buildGrid', () => {
    test('builds the four-line grid CSS block using repeat()', () => {
        const result = buildGrid({ columns: 3, rows: 2, gap: 8 });

        expect(result).toBe(
            'display: grid;\n' +
                'grid-template-columns: repeat(3, 1fr);\n' +
                'grid-template-rows: repeat(2, 1fr);\n' +
                'gap: 8px;',
        );
    });
});
```

- [ ] **Step 2: テストが失敗することを確認**

Run: `npm run test -- cssGenerators`
Expected: FAIL(`./cssGenerators`モジュールが存在しない)

- [ ] **Step 3: `cssGenerators.ts`を実装**

```typescript
export interface BoxShadowInput {
    x: number;
    y: number;
    blur: number;
    spread: number;
    color: string;
    inset: boolean;
}

export function buildBoxShadow(input: BoxShadowInput): string {
    const prefix = input.inset ? 'inset ' : '';

    return `box-shadow: ${prefix}${input.x}px ${input.y}px ${input.blur}px ${input.spread}px ${input.color};`;
}

export interface BorderRadiusInput {
    topLeft: number;
    topRight: number;
    bottomRight: number;
    bottomLeft: number;
}

export function buildBorderRadius(input: BorderRadiusInput): string {
    return `border-radius: ${input.topLeft}px ${input.topRight}px ${input.bottomRight}px ${input.bottomLeft}px;`;
}

export interface GlassmorphismInput {
    opacity: number;
    blur: number;
    borderOpacity: number;
    shadowOpacity: number;
}

export function buildGlassmorphism(input: GlassmorphismInput): string {
    return [
        `background: rgba(255, 255, 255, ${input.opacity});`,
        `backdrop-filter: blur(${input.blur}px);`,
        `border: 1px solid rgba(255, 255, 255, ${input.borderOpacity});`,
        `box-shadow: 0 8px 32px 0 rgba(31, 38, 135, ${input.shadowOpacity});`,
    ].join('\n');
}

export type FlexDirection = 'row' | 'row-reverse' | 'column' | 'column-reverse';
export type JustifyContent =
    | 'flex-start'
    | 'flex-end'
    | 'center'
    | 'space-between'
    | 'space-around'
    | 'space-evenly';
export type AlignItems = 'stretch' | 'flex-start' | 'flex-end' | 'center' | 'baseline';
export type FlexWrap = 'nowrap' | 'wrap' | 'wrap-reverse';

export interface FlexboxInput {
    direction: FlexDirection;
    justifyContent: JustifyContent;
    alignItems: AlignItems;
    wrap: FlexWrap;
    gap: number;
}

export function buildFlexbox(input: FlexboxInput): string {
    return [
        'display: flex;',
        `flex-direction: ${input.direction};`,
        `justify-content: ${input.justifyContent};`,
        `align-items: ${input.alignItems};`,
        `flex-wrap: ${input.wrap};`,
        `gap: ${input.gap}px;`,
    ].join('\n');
}

export interface GridInput {
    columns: number;
    rows: number;
    gap: number;
}

export function buildGrid(input: GridInput): string {
    return [
        'display: grid;',
        `grid-template-columns: repeat(${input.columns}, 1fr);`,
        `grid-template-rows: repeat(${input.rows}, 1fr);`,
        `gap: ${input.gap}px;`,
    ].join('\n');
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `npm run test -- cssGenerators`
Expected: PASS(8テスト)

- [ ] **Step 5: Lint/Format/型チェックを確認**

Run: `npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてエラーなし

- [ ] **Step 6: コミット**

```bash
git add resources/js/lib/cssGenerators.ts resources/js/lib/cssGenerators.test.ts
git commit -m "feat: add CSS generator utility functions for the CSS category"
```

---

### Task 2: Box Shadow(`/css-box-shadow`)

**Files:**
- Create: `app/Http/Controllers/Tools/CssBoxShadowController.php`
- Create: `resources/js/pages/CssBoxShadow.vue`
- Test: `tests/Feature/Tools/CssBoxShadowControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildBoxShadow(input: BoxShadowInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssBoxShadowController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-box-shadow';
    }

    protected function page(): string
    {
        return 'CssBoxShadow';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(アルファベット順、`ColorSchemeController`の直後):

```php
use App\Http\Controllers\Tools\CssBoxShadowController;
```

`base64-encode`のルート群の直前(SEOルート群より前、Colorルート群の直後)に追加:

```php
Route::get('/css-box-shadow', [CssBoxShadowController::class, 'show'])->name('css-box-shadow.show');
Route::post('/css-box-shadow/track', [CssBoxShadowController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-box-shadow.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`color-scheme`エントリの直後に追加:

```php
    [
        'slug' => 'css-box-shadow',
        'name' => 'Box Shadow ジェネレーター',
        'category' => 'css',
        'description' => 'オフセット・ぼかし・色を調整してbox-shadowのCSSコードを生成します。',
        'href' => '/css-box-shadow',
    ],
```

- [ ] **Step 4: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the css-box-shadow page renders', function () {
    $response = $this->get(route('css-box-shadow.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('CssBoxShadow'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('css-box-shadow.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'css-box-shadow')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 5: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/CssBoxShadowControllerTest.php`
Expected: FAIL(`CssBoxShadow`コンポーネントが存在しない)

- [ ] **Step 6: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildBoxShadow } from '@/lib/cssGenerators';

const x = ref(4);
const y = ref(8);
const blur = ref(16);
const spread = ref(0);
const color = ref('#000000');
const inset = ref(false);
const copied = ref(false);

const css = computed(() =>
    buildBoxShadow({
        x: x.value,
        y: y.value,
        blur: blur.value,
        spread: spread.value,
        color: color.value,
        inset: inset.value,
    }),
);

async function copy() {
    try {
        await navigator.clipboard.writeText(css.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([x, y, blur, spread, color, inset], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-box-shadow/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Box Shadow ジェネレーター - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    Box Shadow ジェネレーター
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、box-shadowのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 flex h-40 items-center justify-center rounded-md border border-gray-200 bg-white"
            >
                <div
                    class="h-20 w-20 rounded-md bg-white"
                    :style="css"
                ></div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    X オフセット: {{ x }}px
                    <input v-model.number="x" type="range" min="-50" max="50" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    Y オフセット: {{ y }}px
                    <input v-model.number="y" type="range" min="-50" max="50" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    ぼかし: {{ blur }}px
                    <input v-model.number="blur" type="range" min="0" max="100" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    広がり: {{ spread }}px
                    <input v-model.number="spread" type="range" min="-50" max="50" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    色
                    <input v-model="color" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="inset" type="checkbox" />
                    inset(内側に影)
                </label>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">生成されたCSS</span>
                    <button
                        type="button"
                        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                        @click="copy"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <pre
                    class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                    >{{ css }}</pre
                >
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 7: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/CssBoxShadowControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 8: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 9: コミット**

```bash
git add app/Http/Controllers/Tools/CssBoxShadowController.php resources/js/pages/CssBoxShadow.vue tests/Feature/Tools/CssBoxShadowControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add Box Shadow generator tool"
```

---

### Task 3: Border Radius(`/css-border-radius`)

**Files:**
- Create: `app/Http/Controllers/Tools/CssBorderRadiusController.php`
- Create: `resources/js/pages/CssBorderRadius.vue`
- Test: `tests/Feature/Tools/CssBorderRadiusControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildBorderRadius(input: BorderRadiusInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssBorderRadiusController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-border-radius';
    }

    protected function page(): string
    {
        return 'CssBorderRadius';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(アルファベット順で`CssBorderRadiusController` < `CssBoxShadowController`のため、`ColorSchemeController`の直後・`CssBoxShadowController`の直前):

```php
use App\Http\Controllers\Tools\CssBorderRadiusController;
```

`css-box-shadow`のルート群の直後に追加:

```php
Route::get('/css-border-radius', [CssBorderRadiusController::class, 'show'])->name('css-border-radius.show');
Route::post('/css-border-radius/track', [CssBorderRadiusController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-border-radius.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`css-box-shadow`エントリの直後に追加:

```php
    [
        'slug' => 'css-border-radius',
        'name' => 'Border Radius ジェネレーター',
        'category' => 'css',
        'description' => '4隅を個別に調整してborder-radiusのCSSコードを生成します。',
        'href' => '/css-border-radius',
    ],
```

- [ ] **Step 4: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the css-border-radius page renders', function () {
    $response = $this->get(route('css-border-radius.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('CssBorderRadius'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('css-border-radius.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'css-border-radius')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 5: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/CssBorderRadiusControllerTest.php`
Expected: FAIL(`CssBorderRadius`コンポーネントが存在しない)

- [ ] **Step 6: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildBorderRadius } from '@/lib/cssGenerators';

const topLeft = ref(16);
const topRight = ref(16);
const bottomRight = ref(16);
const bottomLeft = ref(16);
const copied = ref(false);

const css = computed(() =>
    buildBorderRadius({
        topLeft: topLeft.value,
        topRight: topRight.value,
        bottomRight: bottomRight.value,
        bottomLeft: bottomLeft.value,
    }),
);

async function copy() {
    try {
        await navigator.clipboard.writeText(css.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([topLeft, topRight, bottomRight, bottomLeft], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-border-radius/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Border Radius ジェネレーター - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    Border Radius ジェネレーター
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    4隅を個別に調整すると、border-radiusのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="mb-8 flex h-40 items-center justify-center rounded-md border border-gray-200 bg-white">
                <div class="h-24 w-24 bg-gray-900" :style="css"></div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    左上: {{ topLeft }}px
                    <input v-model.number="topLeft" type="range" min="0" max="200" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    右上: {{ topRight }}px
                    <input v-model.number="topRight" type="range" min="0" max="200" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    右下: {{ bottomRight }}px
                    <input v-model.number="bottomRight" type="range" min="0" max="200" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    左下: {{ bottomLeft }}px
                    <input v-model.number="bottomLeft" type="range" min="0" max="200" class="mt-1 w-full" />
                </label>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">生成されたCSS</span>
                    <button
                        type="button"
                        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                        @click="copy"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <pre
                    class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                    >{{ css }}</pre
                >
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 7: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/CssBorderRadiusControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 8: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 9: コミット**

```bash
git add app/Http/Controllers/Tools/CssBorderRadiusController.php resources/js/pages/CssBorderRadius.vue tests/Feature/Tools/CssBorderRadiusControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add Border Radius generator tool"
```

---

### Task 4: Glassmorphism(`/css-glassmorphism`)

**Files:**
- Create: `app/Http/Controllers/Tools/CssGlassmorphismController.php`
- Create: `resources/js/pages/CssGlassmorphism.vue`
- Test: `tests/Feature/Tools/CssGlassmorphismControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildGlassmorphism(input: GlassmorphismInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssGlassmorphismController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-glassmorphism';
    }

    protected function page(): string
    {
        return 'CssGlassmorphism';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(この時点で登録済みの`Css*`は`CssBorderRadiusController`・`CssBoxShadowController`の2つ。アルファベット順で`CssGlassmorphismController`はその後・`FaviconGenerateController`の前に入るため、`CssBoxShadowController`の直後):

```php
use App\Http\Controllers\Tools\CssGlassmorphismController;
```

`css-border-radius`のルート群の直後に追加:

```php
Route::get('/css-glassmorphism', [CssGlassmorphismController::class, 'show'])->name('css-glassmorphism.show');
Route::post('/css-glassmorphism/track', [CssGlassmorphismController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-glassmorphism.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`css-border-radius`エントリの直後に追加:

```php
    [
        'slug' => 'css-glassmorphism',
        'name' => 'Glassmorphism ジェネレーター',
        'category' => 'css',
        'description' => '透明度・ぼかし・影を調整してガラス風カードのCSSコードを生成します。',
        'href' => '/css-glassmorphism',
    ],
```

- [ ] **Step 4: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the css-glassmorphism page renders', function () {
    $response = $this->get(route('css-glassmorphism.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('CssGlassmorphism'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('css-glassmorphism.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'css-glassmorphism')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 5: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/CssGlassmorphismControllerTest.php`
Expected: FAIL(`CssGlassmorphism`コンポーネントが存在しない)

- [ ] **Step 6: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildGlassmorphism } from '@/lib/cssGenerators';

const opacity = ref(0.2);
const blur = ref(10);
const borderOpacity = ref(0.3);
const shadowOpacity = ref(0.37);
const copied = ref(false);

const css = computed(() =>
    buildGlassmorphism({
        opacity: opacity.value,
        blur: blur.value,
        borderOpacity: borderOpacity.value,
        shadowOpacity: shadowOpacity.value,
    }),
);

async function copy() {
    try {
        await navigator.clipboard.writeText(css.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([opacity, blur, borderOpacity, shadowOpacity], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-glassmorphism/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Glassmorphism ジェネレーター - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    Glassmorphism ジェネレーター
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、ガラス風カードのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 flex h-40 items-center justify-center rounded-md bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%23e5e7eb%22/%3E%3Crect x=%2240%22 y=%2240%22 width=%2240%22 height=%2240%22 fill=%22%23e5e7eb%22/%3E%3C/svg%3E')] bg-[length:20px_20px]"
            >
                <div class="h-24 w-40 rounded-lg" :style="css"></div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    透明度: {{ opacity }}
                    <input v-model.number="opacity" type="range" min="0" max="1" step="0.05" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    ぼかし: {{ blur }}px
                    <input v-model.number="blur" type="range" min="0" max="40" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    境界線の透明度: {{ borderOpacity }}
                    <input v-model.number="borderOpacity" type="range" min="0" max="1" step="0.05" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    影の強さ: {{ shadowOpacity }}
                    <input v-model.number="shadowOpacity" type="range" min="0" max="1" step="0.01" class="mt-1 w-full" />
                </label>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">生成されたCSS</span>
                    <button
                        type="button"
                        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                        @click="copy"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <pre
                    class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                    >{{ css }}</pre
                >
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 7: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/CssGlassmorphismControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 8: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 9: コミット**

```bash
git add app/Http/Controllers/Tools/CssGlassmorphismController.php resources/js/pages/CssGlassmorphism.vue tests/Feature/Tools/CssGlassmorphismControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add Glassmorphism generator tool"
```

---

### Task 5: Flexbox(`/css-flexbox`)

**Files:**
- Create: `app/Http/Controllers/Tools/CssFlexboxController.php`
- Create: `resources/js/pages/CssFlexbox.vue`
- Test: `tests/Feature/Tools/CssFlexboxControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildFlexbox(input: FlexboxInput): string`(Task 1)、`FlexDirection`/`JustifyContent`/`AlignItems`/`FlexWrap`型(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssFlexboxController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-flexbox';
    }

    protected function page(): string
    {
        return 'CssFlexbox';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(この時点で登録済みの`Css*`は`CssBorderRadiusController`・`CssBoxShadowController`・`CssGlassmorphismController`の3つ。アルファベット順で`CssFlexboxController`は`CssBoxShadowController`の後・`CssGlassmorphismController`の前に入る):

```php
use App\Http\Controllers\Tools\CssFlexboxController;
```

`css-glassmorphism`のルート群の直後に追加:

```php
Route::get('/css-flexbox', [CssFlexboxController::class, 'show'])->name('css-flexbox.show');
Route::post('/css-flexbox/track', [CssFlexboxController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-flexbox.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`css-glassmorphism`エントリの直後に追加:

```php
    [
        'slug' => 'css-flexbox',
        'name' => 'Flexbox ジェネレーター',
        'category' => 'css',
        'description' => 'プレイグラウンドでFlexboxのプロパティを調整し、CSSコードを生成します。',
        'href' => '/css-flexbox',
    ],
```

- [ ] **Step 4: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the css-flexbox page renders', function () {
    $response = $this->get(route('css-flexbox.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('CssFlexbox'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('css-flexbox.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'css-flexbox')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 5: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/CssFlexboxControllerTest.php`
Expected: FAIL(`CssFlexbox`コンポーネントが存在しない)

- [ ] **Step 6: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildFlexbox } from '@/lib/cssGenerators';
import type {
    AlignItems,
    FlexDirection,
    FlexWrap,
    JustifyContent,
} from '@/lib/cssGenerators';

const direction = ref<FlexDirection>('row');
const justifyContent = ref<JustifyContent>('flex-start');
const alignItems = ref<AlignItems>('stretch');
const wrap = ref<FlexWrap>('nowrap');
const gap = ref(8);
const copied = ref(false);

const css = computed(() =>
    buildFlexbox({
        direction: direction.value,
        justifyContent: justifyContent.value,
        alignItems: alignItems.value,
        wrap: wrap.value,
        gap: gap.value,
    }),
);

const previewStyle = computed(() => ({
    display: 'flex',
    flexDirection: direction.value,
    justifyContent: justifyContent.value,
    alignItems: alignItems.value,
    flexWrap: wrap.value,
    gap: `${gap.value}px`,
}));

async function copy() {
    try {
        await navigator.clipboard.writeText(css.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([direction, justifyContent, alignItems, wrap, gap], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-flexbox/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Flexbox ジェネレーター - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    Flexbox ジェネレーター
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    プロパティを調整すると、下のプレビューが実際に並び替わり、CSSコードが生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 min-h-40 rounded-md border border-gray-200 bg-white p-4"
                :style="previewStyle"
            >
                <div
                    v-for="n in 4"
                    :key="n"
                    class="flex h-16 w-16 items-center justify-center rounded-md bg-gray-900 text-sm font-medium text-white"
                >
                    {{ n }}
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    flex-direction
                    <select v-model="direction" class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                        <option value="row">row</option>
                        <option value="row-reverse">row-reverse</option>
                        <option value="column">column</option>
                        <option value="column-reverse">column-reverse</option>
                    </select>
                </label>
                <label class="text-sm text-gray-700">
                    justify-content
                    <select v-model="justifyContent" class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                        <option value="flex-start">flex-start</option>
                        <option value="flex-end">flex-end</option>
                        <option value="center">center</option>
                        <option value="space-between">space-between</option>
                        <option value="space-around">space-around</option>
                        <option value="space-evenly">space-evenly</option>
                    </select>
                </label>
                <label class="text-sm text-gray-700">
                    align-items
                    <select v-model="alignItems" class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                        <option value="stretch">stretch</option>
                        <option value="flex-start">flex-start</option>
                        <option value="flex-end">flex-end</option>
                        <option value="center">center</option>
                        <option value="baseline">baseline</option>
                    </select>
                </label>
                <label class="text-sm text-gray-700">
                    flex-wrap
                    <select v-model="wrap" class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                        <option value="nowrap">nowrap</option>
                        <option value="wrap">wrap</option>
                        <option value="wrap-reverse">wrap-reverse</option>
                    </select>
                </label>
                <label class="text-sm text-gray-700 sm:col-span-2">
                    gap: {{ gap }}px
                    <input v-model.number="gap" type="range" min="0" max="50" class="mt-1 w-full" />
                </label>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">生成されたCSS</span>
                    <button
                        type="button"
                        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                        @click="copy"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <pre
                    class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                    >{{ css }}</pre
                >
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 7: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/CssFlexboxControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 8: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 9: コミット**

```bash
git add app/Http/Controllers/Tools/CssFlexboxController.php resources/js/pages/CssFlexbox.vue tests/Feature/Tools/CssFlexboxControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add Flexbox generator tool"
```

---

### Task 6: Grid(`/css-grid`)

**Files:**
- Create: `app/Http/Controllers/Tools/CssGridController.php`
- Create: `resources/js/pages/CssGrid.vue`
- Test: `tests/Feature/Tools/CssGridControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildGrid(input: GridInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class CssGridController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'css-grid';
    }

    protected function page(): string
    {
        return 'CssGrid';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(`CssGlassmorphismController`の直後):

```php
use App\Http\Controllers\Tools\CssGridController;
```

`css-flexbox`のルート群の直後に追加:

```php
Route::get('/css-grid', [CssGridController::class, 'show'])->name('css-grid.show');
Route::post('/css-grid/track', [CssGridController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-grid.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`css-flexbox`エントリの直後に追加:

```php
    [
        'slug' => 'css-grid',
        'name' => 'Grid ジェネレーター',
        'category' => 'css',
        'description' => 'プレイグラウンドで列数・行数・gapを調整し、CSS Gridのコードを生成します。',
        'href' => '/css-grid',
    ],
```

- [ ] **Step 4: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the css-grid page renders', function () {
    $response = $this->get(route('css-grid.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('CssGrid'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('css-grid.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'css-grid')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 5: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/CssGridControllerTest.php`
Expected: FAIL(`CssGrid`コンポーネントが存在しない)

- [ ] **Step 6: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildGrid } from '@/lib/cssGenerators';

const columns = ref(3);
const rows = ref(2);
const gap = ref(8);
const copied = ref(false);

const css = computed(() =>
    buildGrid({ columns: columns.value, rows: rows.value, gap: gap.value }),
);

const previewStyle = computed(() => ({
    display: 'grid',
    gridTemplateColumns: `repeat(${columns.value}, 1fr)`,
    gridTemplateRows: `repeat(${rows.value}, 1fr)`,
    gap: `${gap.value}px`,
}));

const cellCount = computed(() => columns.value * rows.value);

async function copy() {
    try {
        await navigator.clipboard.writeText(css.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard unavailable; the value is still visible as text.
    }
}

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch([columns, rows, gap], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/css-grid/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="Grid ジェネレーター - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    Grid ジェネレーター
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    列数・行数・gapを調整すると、下のプレビューに反映され、CSS Gridのコードが生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 min-h-40 rounded-md border border-gray-200 bg-white p-4"
                :style="previewStyle"
            >
                <div
                    v-for="n in cellCount"
                    :key="n"
                    class="flex items-center justify-center rounded-md bg-gray-900 text-sm font-medium text-white"
                >
                    {{ n }}
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <label class="text-sm text-gray-700">
                    列数: {{ columns }}
                    <input v-model.number="columns" type="range" min="1" max="6" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    行数: {{ rows }}
                    <input v-model.number="rows" type="range" min="1" max="6" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    gap: {{ gap }}px
                    <input v-model.number="gap" type="range" min="0" max="50" class="mt-1 w-full" />
                </label>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">生成されたCSS</span>
                    <button
                        type="button"
                        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700"
                        @click="copy"
                    >
                        {{ copied ? 'コピーしました' : 'コピー' }}
                    </button>
                </div>
                <pre
                    class="mt-2 overflow-x-auto rounded-md bg-gray-900 p-4 text-xs text-gray-100"
                    >{{ css }}</pre
                >
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 7: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/CssGridControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 8: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 9: コミット**

```bash
git add app/Http/Controllers/Tools/CssGridController.php resources/js/pages/CssGrid.vue tests/Feature/Tools/CssGridControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add Grid generator tool"
```

---

### Task 7: 動作確認・ドキュメント更新

**Files:**
- Modify: `tests/Feature/Admin/DashboardControllerTest.php`(`->has('stats', 21)` → `->has('stats', 26)`)
- Modify: `TASKS.md`(Phase 10セクション追加・チェック)

**Interfaces:**
- Consumes: すべてのTask 1〜6の成果物。
- Produces: なし(検証タスク)。

- [ ] **Step 1: Admin集計テストのツール数を更新**

`tests/Feature/Admin/DashboardControllerTest.php`の`->has('stats', 21)`を`->has('stats', 26)`に変更する(5ツール追加のため21→26)。

- [ ] **Step 2: バックエンド・フロントの全チェックを実行**

Run: `composer ci:check`
Expected: `npm run lint:check` / `format:check` / `types:check` / `npm run test` / `composer test`(Pint/PHPStan/Pest)がすべてグリーン

- [ ] **Step 3: `npm run build`を実行**

Run: `npm run build`
Expected: ビルドエラーなし

- [ ] **Step 4: `TASKS.md`にPhase 10セクションを追記**

Phase 9セクションの直後、「## 今後の実装順序」の直前に、実装した内容(`cssGenerators.ts`共通基盤・5ツール)をチェック済みとして記述する。「## 今後の実装順序」の行は、SPEC.md §21の残りカテゴリ(Pattern・UI Pattern・Generator追加・Developer改称は完了済みのため実質Pattern以降)を反映する形に更新する。

- [ ] **Step 5: Playwrightで5ツールすべての実ブラウザ動作確認**

`php artisan serve`と`npx vite`をそれぞれ別ポートで起動し、以下を確認する:
- Box Shadow: スライダー操作でプレビューのシャドウと生成CSSコードが連動して変わること
- Border Radius: 4隅を個別に変更してプレビューの角丸が非対称に変化すること
- Glassmorphism: 透明度・ぼかしの変化がプレビューに反映されること
- Flexbox: プロパティ変更でプレビュー内の4つのboxが実際に並び替わること
- Grid: 列数・行数変更でプレビューのセル数が変わること

- [ ] **Step 6: コミット**

```bash
git add tests/Feature/Admin/DashboardControllerTest.php TASKS.md
git commit -m "docs: mark Phase 10 tasks complete in TASKS.md"
```
