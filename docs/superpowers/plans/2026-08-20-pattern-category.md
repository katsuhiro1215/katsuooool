# Patternカテゴリ(Phase 11)Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Patternカテゴリの6ツール(ドット・グリッド・ストライプ・チェッカーボード・ジグザグ・波模様)を実装する。いずれも背景色・パターン色を調整するとリアルタイムでCSSコードが生成される、ブラウザ内完結型のツール。

**Architecture:** CSSカテゴリ(Phase 10)と同じ「`TrackedToolController`継承 + クライアント完結」パターン。CSS文字列生成ロジックは`resources/js/lib/patternGenerators.ts`に純粋関数として集約し、Vitestで単体テストする。全ツール共通で「背景色」「パターン色」の2色構成に統一する。

**Tech Stack:** PHP 8.3 / Laravel 13 / Vue 3 + TypeScript / Vitest / Pest 4

**Spec:** [docs/superpowers/specs/2026-08-20-pattern-category-design.md](../specs/2026-08-20-pattern-category-design.md)

## Global Constraints

- ドキュメント成果物・コード内の文言はすべて日本語(CLAUDE.md)。
- 新規ツール画面はshadcn-vueを使わず、Tailwind CSSで独自に組み立てる(CLAUDE.md UI方針)。
- 各ツールはファイルアップロードを伴わない(既存Color/CSSツールと同型)。
- `PHPStan level 7`(`composer.json`の`types:check`)、ESLint/Prettier/vue-tsc(`npm run lint:check`/`format:check`/`types:check`)がすべてグリーンであること。
- 各ツールの`track`エンドポイントには`throttle:30,1`を適用。
- 全6ツールで「背景色」「パターン色」の2色構成に統一する(設計書§共通仕様)。
- ツール追加のたびに`tests/Feature/Admin/DashboardControllerTest.php`のツール数アサーションを1ずつ増やす(CSSカテゴリのTask運用と同じ理由: `composer test`を各タスク後もグリーンに保つため。開始時点は26)。

---

## 参考: 既存パターン

- `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存): `show()`でInertiaページ表示、`track()`で`ToolEventLogger::log(eventType: 'process')`のみ記録。
- `config/tools.php`: `slug`/`name`/`category`/`description`/`href`の配列。
- `routes/web.php`: 各ツールの`GET`(表示)+`POST /track`(ログ)ルートを追記。
- `tests/Feature/Admin/DashboardControllerTest.php:46`: `->has('stats', 26)` — ツール数が増えるたびにこの数値を更新する(6ツール追加後は32)。
- コピー機能の参考実装: `resources/js/pages/CssBoxShadow.vue`の`copy()`関数。
- プレビュー分離の参考実装: `resources/js/pages/CssGrid.vue`の`previewStyle`(テキスト表示用の`css`と、実際の見た目に適用する`:style`オブジェクトを分離する設計)。

---

### Task 1: 共通基盤(`patternGenerators.ts`)

**Files:**
- Create: `resources/js/lib/patternGenerators.ts`
- Create: `resources/js/lib/patternGenerators.test.ts`

**Interfaces:**
- Consumes: なし(このタスクが基盤)。
- Produces:
  - `export interface DotPatternInput { backgroundColor: string; patternColor: string; radius: number; spacing: number }`
  - `export function buildDotPattern(input: DotPatternInput): string`
  - `export interface GridPatternInput { backgroundColor: string; lineColor: string; lineWidth: number; cellSize: number }`
  - `export function buildGridPattern(input: GridPatternInput): string`
  - `export interface StripePatternInput { backgroundColor: string; patternColor: string; stripeWidth: number; angle: number }`
  - `export function buildStripePattern(input: StripePatternInput): string`
  - `export interface CheckerboardPatternInput { backgroundColor: string; patternColor: string; cellSize: number }`
  - `export function buildCheckerboardPattern(input: CheckerboardPatternInput): string`
  - `export interface ChevronPatternInput { backgroundColor: string; patternColor: string; width: number; height: number }`
  - `export function buildChevronPattern(input: ChevronPatternInput): string`
  - `export interface WavePatternInput { backgroundColor: string; patternColor: string; waveHeight: number; wavelength: number }`
  - `export function buildWavePattern(input: WavePatternInput): string`

- [ ] **Step 1: 失敗するテストを書く**

```typescript
import { describe, expect, test } from 'vitest';

import {
    buildCheckerboardPattern,
    buildChevronPattern,
    buildDotPattern,
    buildGridPattern,
    buildStripePattern,
    buildWavePattern,
} from './patternGenerators';

describe('buildDotPattern', () => {
    test('builds a radial-gradient dot pattern', () => {
        const result = buildDotPattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            radius: 3,
            spacing: 20,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: radial-gradient(circle, #000000 3px, transparent 3px);\n' +
                'background-size: 20px 20px;',
        );
    });
});

describe('buildGridPattern', () => {
    test('builds a two-gradient grid pattern', () => {
        const result = buildGridPattern({
            backgroundColor: '#ffffff',
            lineColor: '#000000',
            lineWidth: 1,
            cellSize: 20,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: linear-gradient(to right, #000000 1px, transparent 1px), linear-gradient(to bottom, #000000 1px, transparent 1px);\n' +
                'background-size: 20px 20px;',
        );
    });
});

describe('buildStripePattern', () => {
    test('builds a repeating-linear-gradient stripe pattern', () => {
        const result = buildStripePattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            stripeWidth: 10,
            angle: 45,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: repeating-linear-gradient(45deg, #000000 0px 10px, #ffffff 10px 20px);',
        );
    });

    test('reflects a different angle and stripe width', () => {
        const result = buildStripePattern({
            backgroundColor: '#fafafa',
            patternColor: '#333333',
            stripeWidth: 5,
            angle: 90,
        });

        expect(result).toContain('repeating-linear-gradient(90deg, #333333 0px 5px, #fafafa 5px 10px)');
    });
});

describe('buildCheckerboardPattern', () => {
    test('builds a two-gradient checkerboard pattern', () => {
        const result = buildCheckerboardPattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            cellSize: 20,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: linear-gradient(45deg, #000000 25%, transparent 25%, transparent 75%, #000000 75%), linear-gradient(45deg, #000000 25%, transparent 25%, transparent 75%, #000000 75%);\n' +
                'background-size: 40px 40px;\n' +
                'background-position: 0 0, 20px 20px;',
        );
    });
});

describe('buildChevronPattern', () => {
    test('builds a four-gradient chevron pattern', () => {
        const result = buildChevronPattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            width: 40,
            height: 20,
        });

        expect(result).toBe(
            'background-color: #ffffff;\n' +
                'background-image: linear-gradient(135deg, #000000 25%, transparent 25%), linear-gradient(225deg, #000000 25%, transparent 25%), linear-gradient(315deg, #000000 25%, transparent 25%), linear-gradient(45deg, #000000 25%, transparent 25%);\n' +
                'background-size: 40px 20px;',
        );
    });
});

describe('buildWavePattern', () => {
    test('builds an SVG data URI wave pattern with URL-encoded content', () => {
        const result = buildWavePattern({
            backgroundColor: '#ffffff',
            patternColor: '#000000',
            waveHeight: 10,
            wavelength: 100,
        });

        expect(result).toContain('background-color: #ffffff;');
        expect(result).toContain('background-image: url("data:image/svg+xml,');
        expect(result).toContain('background-size: 100px 20px;');
        // The SVG's raw '#' and quote characters must be percent-encoded
        // for the data URI to be valid; the literal '#000000' must not
        // appear unescaped inside the url(...) value.
        expect(result).not.toContain('#000000\'');
        expect(result).toContain('%23000000');
    });

    test('scales the SVG dimensions with waveHeight and wavelength', () => {
        const result = buildWavePattern({
            backgroundColor: '#fafafa',
            patternColor: '#111111',
            waveHeight: 15,
            wavelength: 60,
        });

        expect(result).toContain('background-size: 60px 30px;');
    });
});
```

- [ ] **Step 2: テストが失敗することを確認**

Run: `npm run test -- patternGenerators`
Expected: FAIL(`./patternGenerators`モジュールが存在しない)

- [ ] **Step 3: `patternGenerators.ts`を実装**

```typescript
export interface DotPatternInput {
    backgroundColor: string;
    patternColor: string;
    radius: number;
    spacing: number;
}

export function buildDotPattern(input: DotPatternInput): string {
    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: radial-gradient(circle, ${input.patternColor} ${input.radius}px, transparent ${input.radius}px);`,
        `background-size: ${input.spacing}px ${input.spacing}px;`,
    ].join('\n');
}

export interface GridPatternInput {
    backgroundColor: string;
    lineColor: string;
    lineWidth: number;
    cellSize: number;
}

export function buildGridPattern(input: GridPatternInput): string {
    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: linear-gradient(to right, ${input.lineColor} ${input.lineWidth}px, transparent ${input.lineWidth}px), linear-gradient(to bottom, ${input.lineColor} ${input.lineWidth}px, transparent ${input.lineWidth}px);`,
        `background-size: ${input.cellSize}px ${input.cellSize}px;`,
    ].join('\n');
}

export interface StripePatternInput {
    backgroundColor: string;
    patternColor: string;
    stripeWidth: number;
    angle: number;
}

export function buildStripePattern(input: StripePatternInput): string {
    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: repeating-linear-gradient(${input.angle}deg, ${input.patternColor} 0px ${input.stripeWidth}px, ${input.backgroundColor} ${input.stripeWidth}px ${input.stripeWidth * 2}px);`,
    ].join('\n');
}

export interface CheckerboardPatternInput {
    backgroundColor: string;
    patternColor: string;
    cellSize: number;
}

export function buildCheckerboardPattern(input: CheckerboardPatternInput): string {
    const size = input.cellSize * 2;

    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: linear-gradient(45deg, ${input.patternColor} 25%, transparent 25%, transparent 75%, ${input.patternColor} 75%), linear-gradient(45deg, ${input.patternColor} 25%, transparent 25%, transparent 75%, ${input.patternColor} 75%);`,
        `background-size: ${size}px ${size}px;`,
        `background-position: 0 0, ${input.cellSize}px ${input.cellSize}px;`,
    ].join('\n');
}

export interface ChevronPatternInput {
    backgroundColor: string;
    patternColor: string;
    width: number;
    height: number;
}

export function buildChevronPattern(input: ChevronPatternInput): string {
    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: linear-gradient(135deg, ${input.patternColor} 25%, transparent 25%), linear-gradient(225deg, ${input.patternColor} 25%, transparent 25%), linear-gradient(315deg, ${input.patternColor} 25%, transparent 25%), linear-gradient(45deg, ${input.patternColor} 25%, transparent 25%);`,
        `background-size: ${input.width}px ${input.height}px;`,
    ].join('\n');
}

export interface WavePatternInput {
    backgroundColor: string;
    patternColor: string;
    waveHeight: number;
    wavelength: number;
}

export function buildWavePattern(input: WavePatternInput): string {
    const height = input.waveHeight * 2;
    const half = input.wavelength / 2;
    const quarter = input.wavelength / 4;
    const svg =
        `<svg xmlns='http://www.w3.org/2000/svg' width='${input.wavelength}' height='${height}' ` +
        `viewBox='0 0 ${input.wavelength} ${height}'>` +
        `<path d='M0 ${input.waveHeight} Q${quarter} 0 ${half} ${input.waveHeight} T${input.wavelength} ${input.waveHeight}' ` +
        `stroke='${input.patternColor}' fill='none' stroke-width='2'/></svg>`;
    const encoded = encodeURIComponent(svg);

    return [
        `background-color: ${input.backgroundColor};`,
        `background-image: url("data:image/svg+xml,${encoded}");`,
        `background-size: ${input.wavelength}px ${height}px;`,
    ].join('\n');
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `npm run test -- patternGenerators`
Expected: PASS(7テスト)

- [ ] **Step 5: Lint/Format/型チェックを確認**

Run: `npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてエラーなし

- [ ] **Step 6: コミット**

```bash
git add resources/js/lib/patternGenerators.ts resources/js/lib/patternGenerators.test.ts
git commit -m "feat: add pattern generator utility functions for the Pattern category"
```

---

### Task 2: ドット(`/pattern-dot`)

**Files:**
- Create: `app/Http/Controllers/Tools/PatternDotController.php`
- Create: `resources/js/pages/PatternDot.vue`
- Test: `tests/Feature/Tools/PatternDotControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`
- Modify: `tests/Feature/Admin/DashboardControllerTest.php`(`->has('stats', 26)` → `->has('stats', 27)`)

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildDotPattern(input: DotPatternInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternDotController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-dot';
    }

    protected function page(): string
    {
        return 'PatternDot';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(アルファベット順、`OgpImageController`の直後・`SeoDescriptionCheckController`の直前 — このタスクが最初のPattern*追加のため単純挿入):

```php
use App\Http\Controllers\Tools\PatternDotController;
```

`structured-data-check`のルート群の直後(ファイル末尾、`require`の直前)に追加:

```php
Route::get('/pattern-dot', [PatternDotController::class, 'show'])->name('pattern-dot.show');
Route::post('/pattern-dot/track', [PatternDotController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-dot.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

配列の末尾(`structured-data-check`エントリの後)に追加:

```php
    [
        'slug' => 'pattern-dot',
        'name' => 'ドットパターン',
        'category' => 'pattern',
        'description' => '背景色・パターン色・ドットサイズ・間隔を調整してドット柄のCSSコードを生成します。',
        'href' => '/pattern-dot',
    ],
```

- [ ] **Step 4: `DashboardControllerTest`のツール数を更新**

`tests/Feature/Admin/DashboardControllerTest.php:46`の`->has('stats', 26)`を`->has('stats', 27)`に変更する。

- [ ] **Step 5: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the pattern-dot page renders', function () {
    $response = $this->get(route('pattern-dot.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternDot'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-dot.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-dot')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 6: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/PatternDotControllerTest.php`
Expected: FAIL(`PatternDot`コンポーネントが存在しない)

- [ ] **Step 7: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildDotPattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const radius = ref(3);
const spacing = ref(20);
const copied = ref(false);

const css = computed(() =>
    buildDotPattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        radius: radius.value,
        spacing: spacing.value,
    }),
);

const previewStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    backgroundImage: `radial-gradient(circle, ${patternColor.value} ${radius.value}px, transparent ${radius.value}px)`,
    backgroundSize: `${spacing.value}px ${spacing.value}px`,
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

watch([backgroundColor, patternColor, radius, spacing], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-dot/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ドットパターン - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    ドットパターン
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、ドット柄の背景パターンのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 h-48 rounded-md border border-gray-200"
                :style="previewStyle"
            ></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    背景色
                    <input v-model="backgroundColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    パターン色
                    <input v-model="patternColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    ドットサイズ: {{ radius }}px
                    <input v-model.number="radius" type="range" min="1" max="20" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    間隔: {{ spacing }}px
                    <input v-model.number="spacing" type="range" min="10" max="100" class="mt-1 w-full" />
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

- [ ] **Step 8: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/PatternDotControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 9: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 10: コミット**

```bash
git add app/Http/Controllers/Tools/PatternDotController.php resources/js/pages/PatternDot.vue tests/Feature/Tools/PatternDotControllerTest.php tests/Feature/Admin/DashboardControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add dot pattern generator tool"
```

---

### Task 3: グリッド(`/pattern-grid`)

**Files:**
- Create: `app/Http/Controllers/Tools/PatternGridController.php`
- Create: `resources/js/pages/PatternGrid.vue`
- Test: `tests/Feature/Tools/PatternGridControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`
- Modify: `tests/Feature/Admin/DashboardControllerTest.php`(`->has('stats', 27)` → `->has('stats', 28)`)

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildGridPattern(input: GridPatternInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternGridController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-grid';
    }

    protected function page(): string
    {
        return 'PatternGrid';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(この時点で登録済みの`Pattern*`は`PatternDotController`のみ。アルファベット順で`PatternGridController`は`PatternDotController`の後・`SeoDescriptionCheckController`の前に入るため、`PatternDotController`の直後):

```php
use App\Http\Controllers\Tools\PatternGridController;
```

`pattern-dot`のルート群の直後に追加:

```php
Route::get('/pattern-grid', [PatternGridController::class, 'show'])->name('pattern-grid.show');
Route::post('/pattern-grid/track', [PatternGridController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-grid.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`pattern-dot`エントリの直後に追加:

```php
    [
        'slug' => 'pattern-grid',
        'name' => 'グリッドパターン',
        'category' => 'pattern',
        'description' => '背景色・線の色・線の太さ・マス目サイズを調整して格子柄のCSSコードを生成します。',
        'href' => '/pattern-grid',
    ],
```

- [ ] **Step 4: `DashboardControllerTest`のツール数を更新**

`->has('stats', 27)`を`->has('stats', 28)`に変更する。

- [ ] **Step 5: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the pattern-grid page renders', function () {
    $response = $this->get(route('pattern-grid.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternGrid'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-grid.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-grid')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 6: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/PatternGridControllerTest.php`
Expected: FAIL(`PatternGrid`コンポーネントが存在しない)

- [ ] **Step 7: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildGridPattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const lineColor = ref('#000000');
const lineWidth = ref(1);
const cellSize = ref(20);
const copied = ref(false);

const css = computed(() =>
    buildGridPattern({
        backgroundColor: backgroundColor.value,
        lineColor: lineColor.value,
        lineWidth: lineWidth.value,
        cellSize: cellSize.value,
    }),
);

const previewStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    backgroundImage: `linear-gradient(to right, ${lineColor.value} ${lineWidth.value}px, transparent ${lineWidth.value}px), linear-gradient(to bottom, ${lineColor.value} ${lineWidth.value}px, transparent ${lineWidth.value}px)`,
    backgroundSize: `${cellSize.value}px ${cellSize.value}px`,
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

watch([backgroundColor, lineColor, lineWidth, cellSize], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-grid/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="グリッドパターン - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    グリッドパターン
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、格子柄の背景パターンのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 h-48 rounded-md border border-gray-200"
                :style="previewStyle"
            ></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    背景色
                    <input v-model="backgroundColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    パターン色(線の色)
                    <input v-model="lineColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    線の太さ: {{ lineWidth }}px
                    <input v-model.number="lineWidth" type="range" min="1" max="5" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    マス目サイズ: {{ cellSize }}px
                    <input v-model.number="cellSize" type="range" min="10" max="100" class="mt-1 w-full" />
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

- [ ] **Step 8: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/PatternGridControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 9: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 10: コミット**

```bash
git add app/Http/Controllers/Tools/PatternGridController.php resources/js/pages/PatternGrid.vue tests/Feature/Tools/PatternGridControllerTest.php tests/Feature/Admin/DashboardControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add grid pattern generator tool"
```

---

### Task 4: ストライプ(`/pattern-stripe`)

**Files:**
- Create: `app/Http/Controllers/Tools/PatternStripeController.php`
- Create: `resources/js/pages/PatternStripe.vue`
- Test: `tests/Feature/Tools/PatternStripeControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`
- Modify: `tests/Feature/Admin/DashboardControllerTest.php`(`->has('stats', 28)` → `->has('stats', 29)`)

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildStripePattern(input: StripePatternInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternStripeController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-stripe';
    }

    protected function page(): string
    {
        return 'PatternStripe';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(この時点で登録済みの`Pattern*`は`PatternDotController`・`PatternGridController`。アルファベット順で`PatternStripeController`はその後・`SeoDescriptionCheckController`の前に入るため、`PatternGridController`の直後):

```php
use App\Http\Controllers\Tools\PatternStripeController;
```

`pattern-grid`のルート群の直後に追加:

```php
Route::get('/pattern-stripe', [PatternStripeController::class, 'show'])->name('pattern-stripe.show');
Route::post('/pattern-stripe/track', [PatternStripeController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-stripe.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`pattern-grid`エントリの直後に追加:

```php
    [
        'slug' => 'pattern-stripe',
        'name' => 'ストライプパターン',
        'category' => 'pattern',
        'description' => '背景色・パターン色・幅・角度を調整してストライプ柄のCSSコードを生成します。',
        'href' => '/pattern-stripe',
    ],
```

- [ ] **Step 4: `DashboardControllerTest`のツール数を更新**

`->has('stats', 28)`を`->has('stats', 29)`に変更する。

- [ ] **Step 5: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the pattern-stripe page renders', function () {
    $response = $this->get(route('pattern-stripe.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternStripe'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-stripe.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-stripe')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 6: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/PatternStripeControllerTest.php`
Expected: FAIL(`PatternStripe`コンポーネントが存在しない)

- [ ] **Step 7: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildStripePattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const stripeWidth = ref(10);
const angle = ref(45);
const copied = ref(false);

const css = computed(() =>
    buildStripePattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        stripeWidth: stripeWidth.value,
        angle: angle.value,
    }),
);

const previewStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    backgroundImage: `repeating-linear-gradient(${angle.value}deg, ${patternColor.value} 0px ${stripeWidth.value}px, ${backgroundColor.value} ${stripeWidth.value}px ${stripeWidth.value * 2}px)`,
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

watch([backgroundColor, patternColor, stripeWidth, angle], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-stripe/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ストライプパターン - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    ストライプパターン
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、ストライプ柄の背景パターンのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 h-48 rounded-md border border-gray-200"
                :style="previewStyle"
            ></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    背景色
                    <input v-model="backgroundColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    パターン色
                    <input v-model="patternColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    ストライプ幅: {{ stripeWidth }}px
                    <input v-model.number="stripeWidth" type="range" min="2" max="50" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    角度: {{ angle }}度
                    <input v-model.number="angle" type="range" min="0" max="180" class="mt-1 w-full" />
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

- [ ] **Step 8: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/PatternStripeControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 9: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 10: コミット**

```bash
git add app/Http/Controllers/Tools/PatternStripeController.php resources/js/pages/PatternStripe.vue tests/Feature/Tools/PatternStripeControllerTest.php tests/Feature/Admin/DashboardControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add stripe pattern generator tool"
```

---

### Task 5: チェッカーボード(`/pattern-checkerboard`)

**Files:**
- Create: `app/Http/Controllers/Tools/PatternCheckerboardController.php`
- Create: `resources/js/pages/PatternCheckerboard.vue`
- Test: `tests/Feature/Tools/PatternCheckerboardControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`
- Modify: `tests/Feature/Admin/DashboardControllerTest.php`(`->has('stats', 29)` → `->has('stats', 30)`)

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildCheckerboardPattern(input: CheckerboardPatternInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternCheckerboardController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-checkerboard';
    }

    protected function page(): string
    {
        return 'PatternCheckerboard';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(この時点で登録済みの`Pattern*`は`PatternDotController`・`PatternGridController`・`PatternStripeController`。アルファベット順で`PatternCheckerboardController`は"Checkerboard" < "Dot"のため**先頭**、つまり`OgpImageController`の直後・`PatternDotController`の直前に入る):

```php
use App\Http\Controllers\Tools\PatternCheckerboardController;
```

`structured-data-check`のルート群の直後・`pattern-dot`のルート群の直前に追加(ルート定義自体はアルファベット順を要求されないため、末尾に追記してよい。ただし`use`文の位置は上記の通り厳守すること):

```php
Route::get('/pattern-checkerboard', [PatternCheckerboardController::class, 'show'])->name('pattern-checkerboard.show');
Route::post('/pattern-checkerboard/track', [PatternCheckerboardController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-checkerboard.track');
```

このRoute::ブロックはファイル内のどこに置いても動作に影響しないため、`pattern-stripe`のルート群の直後(ファイル末尾)に追加してよい。

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`pattern-stripe`エントリの直後に追加:

```php
    [
        'slug' => 'pattern-checkerboard',
        'name' => 'チェッカーボードパターン',
        'category' => 'pattern',
        'description' => '背景色・パターン色・マス目サイズを調整して市松模様のCSSコードを生成します。',
        'href' => '/pattern-checkerboard',
    ],
```

- [ ] **Step 4: `DashboardControllerTest`のツール数を更新**

`->has('stats', 29)`を`->has('stats', 30)`に変更する。

- [ ] **Step 5: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the pattern-checkerboard page renders', function () {
    $response = $this->get(route('pattern-checkerboard.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternCheckerboard'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-checkerboard.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-checkerboard')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 6: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/PatternCheckerboardControllerTest.php`
Expected: FAIL(`PatternCheckerboard`コンポーネントが存在しない)

- [ ] **Step 7: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildCheckerboardPattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const cellSize = ref(20);
const copied = ref(false);

const css = computed(() =>
    buildCheckerboardPattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        cellSize: cellSize.value,
    }),
);

const previewStyle = computed(() => {
    const size = cellSize.value * 2;

    return {
        backgroundColor: backgroundColor.value,
        backgroundImage: `linear-gradient(45deg, ${patternColor.value} 25%, transparent 25%, transparent 75%, ${patternColor.value} 75%), linear-gradient(45deg, ${patternColor.value} 25%, transparent 25%, transparent 75%, ${patternColor.value} 75%)`,
        backgroundSize: `${size}px ${size}px`,
        backgroundPosition: `0 0, ${cellSize.value}px ${cellSize.value}px`,
    };
});

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

watch([backgroundColor, patternColor, cellSize], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-checkerboard/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="チェッカーボードパターン - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    チェッカーボードパターン
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、市松模様の背景パターンのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 h-48 rounded-md border border-gray-200"
                :style="previewStyle"
            ></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    背景色
                    <input v-model="backgroundColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    パターン色
                    <input v-model="patternColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700 sm:col-span-2">
                    マス目サイズ: {{ cellSize }}px
                    <input v-model.number="cellSize" type="range" min="10" max="100" class="mt-1 w-full" />
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

- [ ] **Step 8: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/PatternCheckerboardControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 9: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 10: コミット**

```bash
git add app/Http/Controllers/Tools/PatternCheckerboardController.php resources/js/pages/PatternCheckerboard.vue tests/Feature/Tools/PatternCheckerboardControllerTest.php tests/Feature/Admin/DashboardControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add checkerboard pattern generator tool"
```

---

### Task 6: ジグザグ(`/pattern-chevron`)

**Files:**
- Create: `app/Http/Controllers/Tools/PatternChevronController.php`
- Create: `resources/js/pages/PatternChevron.vue`
- Test: `tests/Feature/Tools/PatternChevronControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`
- Modify: `tests/Feature/Admin/DashboardControllerTest.php`(`->has('stats', 30)` → `->has('stats', 31)`)

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildChevronPattern(input: ChevronPatternInput): string`(Task 1)。
- Produces: なし(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternChevronController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-chevron';
    }

    protected function page(): string
    {
        return 'PatternChevron';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(この時点で登録済みの`Pattern*`は`PatternCheckerboardController`・`PatternDotController`・`PatternGridController`・`PatternStripeController`。アルファベット順で"Checkerboard" < "Chevron" < "Dot"(4文字目 'c' < 'v')のため、`PatternCheckerboardController`の直後・`PatternDotController`の直前に入る):

```php
use App\Http\Controllers\Tools\PatternChevronController;
```

Route::ブロックはファイル内の位置に依存しないため、`pattern-checkerboard`のルート群の直後(ファイル末尾)に追加してよい:

```php
Route::get('/pattern-chevron', [PatternChevronController::class, 'show'])->name('pattern-chevron.show');
Route::post('/pattern-chevron/track', [PatternChevronController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-chevron.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`pattern-checkerboard`エントリの直後に追加:

```php
    [
        'slug' => 'pattern-chevron',
        'name' => 'ジグザグパターン',
        'category' => 'pattern',
        'description' => '背景色・パターン色・幅・高さを調整して山形(シェブロン)柄のCSSコードを生成します。',
        'href' => '/pattern-chevron',
    ],
```

- [ ] **Step 4: `DashboardControllerTest`のツール数を更新**

`->has('stats', 30)`を`->has('stats', 31)`に変更する。

- [ ] **Step 5: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the pattern-chevron page renders', function () {
    $response = $this->get(route('pattern-chevron.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternChevron'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-chevron.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-chevron')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 6: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/PatternChevronControllerTest.php`
Expected: FAIL(`PatternChevron`コンポーネントが存在しない)

- [ ] **Step 7: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildChevronPattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const width = ref(40);
const height = ref(20);
const copied = ref(false);

const css = computed(() =>
    buildChevronPattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        width: width.value,
        height: height.value,
    }),
);

const previewStyle = computed(() => ({
    backgroundColor: backgroundColor.value,
    backgroundImage: `linear-gradient(135deg, ${patternColor.value} 25%, transparent 25%), linear-gradient(225deg, ${patternColor.value} 25%, transparent 25%), linear-gradient(315deg, ${patternColor.value} 25%, transparent 25%), linear-gradient(45deg, ${patternColor.value} 25%, transparent 25%)`,
    backgroundSize: `${width.value}px ${height.value}px`,
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

watch([backgroundColor, patternColor, width, height], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-chevron/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="ジグザグパターン - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    ジグザグパターン
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、山形(シェブロン)柄の背景パターンのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 h-48 rounded-md border border-gray-200"
                :style="previewStyle"
            ></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    背景色
                    <input v-model="backgroundColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    パターン色
                    <input v-model="patternColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    幅: {{ width }}px
                    <input v-model.number="width" type="range" min="10" max="100" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    高さ: {{ height }}px
                    <input v-model.number="height" type="range" min="5" max="50" class="mt-1 w-full" />
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

- [ ] **Step 8: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/PatternChevronControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 9: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 10: コミット**

```bash
git add app/Http/Controllers/Tools/PatternChevronController.php resources/js/pages/PatternChevron.vue tests/Feature/Tools/PatternChevronControllerTest.php tests/Feature/Admin/DashboardControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add chevron pattern generator tool"
```

---

### Task 7: 波模様(`/pattern-wave`)

**Files:**
- Create: `app/Http/Controllers/Tools/PatternWaveController.php`
- Create: `resources/js/pages/PatternWave.vue`
- Test: `tests/Feature/Tools/PatternWaveControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`
- Modify: `tests/Feature/Admin/DashboardControllerTest.php`(`->has('stats', 31)` → `->has('stats', 32)`)

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(既存)、`buildWavePattern(input: WavePatternInput): string`(Task 1)。
- Produces: なし(独立ツール、Patternカテゴリ最後のツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternWaveController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-wave';
    }

    protected function page(): string
    {
        return 'PatternWave';
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加(この時点で登録済みの`Pattern*`は`PatternCheckerboardController`・`PatternChevronController`・`PatternDotController`・`PatternGridController`・`PatternStripeController`。アルファベット順で`PatternWaveController`は"W"が最後(Stripeより後)のため、`PatternStripeController`の直後・`SeoDescriptionCheckController`の直前に入る):

```php
use App\Http\Controllers\Tools\PatternWaveController;
```

Route::ブロックはファイル内の位置に依存しないため、`pattern-chevron`のルート群の直後(ファイル末尾)に追加してよい:

```php
Route::get('/pattern-wave', [PatternWaveController::class, 'show'])->name('pattern-wave.show');
Route::post('/pattern-wave/track', [PatternWaveController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-wave.track');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

`pattern-chevron`エントリの直後に追加:

```php
    [
        'slug' => 'pattern-wave',
        'name' => '波模様パターン',
        'category' => 'pattern',
        'description' => '背景色・パターン色・波の高さ・波長を調整して波模様のCSSコードを生成します。',
        'href' => '/pattern-wave',
    ],
```

- [ ] **Step 4: `DashboardControllerTest`のツール数を更新**

`->has('stats', 31)`を`->has('stats', 32)`に変更する。

- [ ] **Step 5: 失敗するFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the pattern-wave page renders', function () {
    $response = $this->get(route('pattern-wave.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('PatternWave'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('pattern-wave.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'pattern-wave')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 6: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/PatternWaveControllerTest.php`
Expected: FAIL(`PatternWave`コンポーネントが存在しない)

- [ ] **Step 7: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { buildWavePattern } from '@/lib/patternGenerators';

const backgroundColor = ref('#ffffff');
const patternColor = ref('#000000');
const waveHeight = ref(10);
const wavelength = ref(100);
const copied = ref(false);

const css = computed(() =>
    buildWavePattern({
        backgroundColor: backgroundColor.value,
        patternColor: patternColor.value,
        waveHeight: waveHeight.value,
        wavelength: wavelength.value,
    }),
);

// The preview reuses the same generated CSS string directly: unlike the
// gradient-based tools, the wave pattern's background-image is a single
// SVG data URI, so there is no separate object-form property list to
// bind — the css string IS the complete, valid background shorthand set.
const previewStyle = computed(() => css.value);

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

watch([backgroundColor, patternColor, waveHeight, wavelength], () => {
    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/pattern-wave/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="波模様パターン - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    波模様パターン
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    値を調整すると、波模様の背景パターンのCSSコードがリアルタイムで生成されます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="mb-8 h-48 rounded-md border border-gray-200"
                :style="previewStyle"
            ></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-gray-700">
                    背景色
                    <input v-model="backgroundColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    パターン色
                    <input v-model="patternColor" type="color" class="mt-1 h-9 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    波の高さ: {{ waveHeight }}px
                    <input v-model.number="waveHeight" type="range" min="5" max="30" class="mt-1 w-full" />
                </label>
                <label class="text-sm text-gray-700">
                    波長: {{ wavelength }}px
                    <input v-model.number="wavelength" type="range" min="20" max="200" class="mt-1 w-full" />
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

Note: `previewStyle`が`css.value`(改行区切りの`property: value;`文字列)をそのまま`:style`にバインドしている点は、CSSカテゴリの`CssGlassmorphism.vue`と同じ手法(Vueは文字列styleを`el.style.cssText`として適用するため、複数行のCSS宣言も正しく反映される)。

- [ ] **Step 8: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/PatternWaveControllerTest.php`
Expected: PASS(2テスト)

- [ ] **Step 9: `composer test`とフロントの各チェックを確認**

Run: `composer test && npm run lint:check && npm run format:check && npm run types:check`
Expected: すべてグリーン

- [ ] **Step 10: コミット**

```bash
git add app/Http/Controllers/Tools/PatternWaveController.php resources/js/pages/PatternWave.vue tests/Feature/Tools/PatternWaveControllerTest.php tests/Feature/Admin/DashboardControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add wave pattern generator tool"
```

---

### Task 8: 動作確認・ドキュメント更新

**Files:**
- Modify: `TASKS.md`(Phase 11セクション追加・チェック)
- Modify: `SPEC.md`(§20/§21.1のPattern該当箇所を実装済みに更新、§25として新規セクション追加)

**Interfaces:**
- Consumes: すべてのTask 1〜7の成果物。
- Produces: なし(検証タスク)。

- [ ] **Step 1: Admin集計テストのツール数を確認**

`tests/Feature/Admin/DashboardControllerTest.php`が`->has('stats', 32)`になっていることを確認する(Task 2〜7で26→32まで段階的に更新済みのはず)。異なる場合は原因を調査すること。

- [ ] **Step 2: バックエンド・フロントの全チェックを実行**

Run: `composer ci:check`
Expected: `npm run lint:check` / `format:check` / `types:check` / `npm run test` / `composer test`(Pint/PHPStan/Pest)がすべてグリーン

- [ ] **Step 3: `npm run build`を実行**

Run: `npm run build`
Expected: ビルドエラーなし

- [ ] **Step 4: `TASKS.md`にPhase 11セクションを追記**

Phase 10セクションの直後、「## 今後の実装順序」の直前に、実装した内容(`patternGenerators.ts`共通基盤・6ツール)をチェック済みとして記述する。Phase 10セクションと同じ構成(`### 11.0 共通基盤`、`### 11.1 ドット(`/pattern-dot`)`〜`### 11.6 波模様(`/pattern-wave`)`、`### 11.7 動作確認`)に揃える。「## 今後の実装順序」の行を、SPEC.md §21.1の残りカテゴリ(UI Pattern・Generator追加)を反映する形に更新する。

- [ ] **Step 5: `SPEC.md`にPatternカテゴリのセクションを追加**

`## 24. CSSカテゴリツール仕様(Phase 10)`の直後(現在SPEC.mdの最後のセクションのはず)に`## 25. Patternカテゴリツール仕様(Phase 11)`を追加する。§24の構成(短い導入文 + `### 25.N`のツールごとの短い説明)に合わせ、6ツールそれぞれの操作・出力を簡潔に記述する。

`## 20. スコープ外(将来のPhase)`のPattern関連の記述(§24追加時に「Pattern・UI Pattern・Generatorカテゴリへのツール追加」に修正済みのはず)を、Patternが実装済みになったことを反映して「UI Pattern・Generatorカテゴリへのツール追加」に更新する。

`### 21.1 カテゴリ拡張案`のPatternの行に「(Phase 11で実装済み、§25参照)」を追記する(§24でCSSの行に行った修正と同じ形式)。

- [ ] **Step 6: Playwrightで6ツールすべての実ブラウザ動作確認**

`php artisan serve`と`npx vite`をそれぞれ別ポートで起動し、以下を確認する:
- ドット・グリッド・ストライプ・チェッカーボード・ジグザグ: 色・サイズを調整するとプレビューと生成CSSコードが連動して変わること
- 波模様: 波の高さ・波長を調整するとプレビューのSVGパターンと生成CSSコードが連動して変わること、コピーしたCSSコードが有効な`background-image: url("data:image/svg+xml,...")`形式になっていること

- [ ] **Step 7: コミット**

```bash
git add TASKS.md SPEC.md
git commit -m "docs: mark Phase 11 tasks complete in TASKS.md and SPEC.md"
```
