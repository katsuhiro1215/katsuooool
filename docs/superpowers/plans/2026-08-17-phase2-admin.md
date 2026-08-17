# Phase 2 Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/admin/login` によるAdmin専用ログインと、`/admin/dashboard` によるツール別利用状況の集計表示を実装する。

**Architecture:** Phase 0 で作った `admin` guard(`admins` テーブル・`Admin` モデル)を使い、Fortifyの `web` guardとは完全に独立したセッション認証フローを追加する。ダッシュボードは Phase 0/1 で記録済みの `tool_events` テーブルを `config('tools')` のツール一覧と突き合わせて集計する読み取り専用ページ。新規登録画面は作らず、既存の `admin:create` Artisanコマンドのみで初期アカウントを作成する。

**Tech Stack:** PHP 8.3 / Laravel 13 / Pest 4 / Inertia.js v3 / Vue 3 + TypeScript / Tailwind CSS

**Spec:** `/Users/katsuhiro.k1215/Project/katsuooool/SPEC.md`(セクション 8)/ `TASKS.md`(Phase 2)

## Global Constraints

- Admin登録画面は作らない。初期アカウントは既存の `admin:create {name} {email} {password}` コマンドで作成する(SPEC.md §8)。
- ログイン試行にレート制限をかける(SPEC.md §8)。
- `/admin/dashboard` は `auth:admin` ミドルウェアで保護し、`web`/Fortify側の認証状態とは独立させる(Phase 0で確立した `admin` guardの分離方針を維持)。
- 新規ツール画面と同様、Admin画面も shadcn-vue を使わず Tailwind CSS で独自実装する(TASKS.md 2.1「独自Tailwind UI」)。
- 「ダウンロード回数」の集計について: 現在 `ToolEventLogger` は `upload`/`process` の2種類のイベントのみを記録しており、実際のダウンロードクリックを記録する仕組みは無い(`ToolDownloadController` は汎用の署名付きURL配信コントローラで、どのツールのダウンロードかを直接知らない)。本Phaseではこれを変更せず、`process` イベントの `file_count`(圧縮に成功しダウンロード可能になったファイル数)の合計を「ダウンロード回数」の近似値として表示する。将来、真のダウンロードクリック計測が必要になった場合は別途イベント種別を追加する。
- テストはPest。`tests/Feature` 配下は自動的に `RefreshDatabase` が適用される。
- モデルの `$fillable`/`$hidden` は既存慣例通り PHP属性(`#[Fillable]`/`#[Hidden]`)を使う。

---

## Task 1: Admin認証ルーティング・コントローラー・レート制限

**Files:**
- Create: `app/Http/Controllers/Admin/AuthenticatedSessionController.php`
- Create: `routes/admin.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Admin/AuthenticatedSessionControllerTest.php`

**Interfaces:**
- Produces:
  - `GET /admin/login` → ルート名 `admin.login`。`guest:admin` ミドルウェアでガード。Inertiaページ `admin/Login` をレンダリング。
  - `POST /admin/login` → ルート名 `admin.login.store`。`guest:admin` + `throttle:admin-login` ミドルウェア。成功時 `admin.dashboard` へリダイレクト、失敗時 `email` フィールドにバリデーションエラーを返す。
  - `POST /admin/logout` → ルート名 `admin.logout`。`auth:admin` ミドルウェア。`admin` guardをログアウトしセッションを再生成、`admin.login` へリダイレクト。
  - 名前付きレートリミッター `admin-login`(`RateLimiter::for('admin-login', ...)`、`AppServiceProvider::boot()` に追加。5回/分、メールアドレス(小文字化)+IPの組み合わせでキー化。Fortifyの `login` リミッターと同じ設計方針)。

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/AuthenticatedSessionControllerTest.php`:

```php
<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

test('the admin login page can be rendered', function () {
    $response = $this->get(route('admin.login'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('admin/Login'));
});

test('an admin can log in with correct credentials', function () {
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated('admin');
});

test('an admin cannot log in with an incorrect password', function () {
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest('admin');
});

test('logging in as an admin does not authenticate the web guard', function () {
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $this->assertGuest('web');
});

test('an already-authenticated admin visiting the login page is redirected to the admin dashboard, not the user dashboard', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.login'));

    $response->assertRedirect(route('admin.dashboard'));
});

test('an authenticated admin can log out', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
});

test('login attempts are rate limited after repeated failures', function () {
    // Laravel's throttle middleware hashes the rate-limit cache key by
    // default (ThrottleRequests::$shouldHashKeys), so pre-clearing a
    // hand-constructed key string is unreliable. Use an email unique to
    // this test instead, so its throttle key can't collide with attempts
    // made by any other test in this file against the same cache store.
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'ratelimit-admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('admin.login.store'), [
            'email' => 'ratelimit-admin@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('admin.login.store'), [
        'email' => 'ratelimit-admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=AuthenticatedSessionControllerTest`
Expected: FAIL(ルート `admin.login` 等が存在しない)

- [ ] **Step 3: レートリミッターを登録**

`app/Providers/AppServiceProvider.php` を以下に置き換える:

```php
<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('admin-login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Laravel's default RedirectIfAuthenticated (the 'guest' middleware
        // alias) redirects to route('dashboard') whenever ANY of the
        // checked guards is authenticated — which would send an
        // already-logged-in admin visiting /admin/login to the Fortify
        // user dashboard at /dashboard instead of /admin/dashboard. This
        // override checks the admin guard specifically and only changes
        // behavior for that case; the 'web' guest middleware used
        // elsewhere (Fortify's own /login) is unaffected since an
        // unauthenticated-as-admin visitor falls through to the default.
        RedirectIfAuthenticated::redirectUsing(
            fn (Request $request) => Auth::guard('admin')->check()
                ? route('admin.dashboard')
                : route('dashboard')
        );
    }
}
```

- [ ] **Step 4: コントローラーを実装**

`app/Http/Controllers/Admin/AuthenticatedSessionController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('admin/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
```

- [ ] **Step 5: ルートファイルを作成**

`routes/admin.php`:

```php
<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
```

- [ ] **Step 6: routes/web.php から require**

`routes/web.php` の末尾(`require __DIR__.'/settings.php';` の下)に追記:

```php
require __DIR__.'/admin.php';
```

- [ ] **Step 7: テストを再実行**

Run: `php artisan test --filter=AuthenticatedSessionControllerTest`
Expected: PASS(7 tests)

- [ ] **Step 8: コミット**

```bash
git add app/Http/Controllers/Admin/AuthenticatedSessionController.php app/Providers/AppServiceProvider.php routes/admin.php routes/web.php tests/Feature/Admin/AuthenticatedSessionControllerTest.php
git commit -m "feat: add admin login/logout with rate-limited authentication"
```

---

## Task 2: Adminログインページ(フロントエンド)

**Files:**
- Create: `resources/js/pages/admin/Login.vue`

**Interfaces:**
- Consumes: `POST /admin/login`(Task 1)。Inertiaの `useForm` でPOSTし、バリデーションエラー(`errors.email`)を表示する。

- [ ] **Step 1: ページコンポーネントを実装**

`resources/js/pages/admin/Login.vue`:

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Admin ログイン" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="text-lg font-bold text-gray-900">Admin ログイン</h1>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">メールアドレス</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">パスワード</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-gray-300" />
                    ログイン状態を保持する
                </label>

                <button
                    type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="form.processing"
                >
                    ログイン
                </button>
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 2: 型・Lintチェック**

Run: `npx vue-tsc --noEmit && npm run lint:check`
Expected: エラーなし

- [ ] **Step 3: コミット**

```bash
git add resources/js/pages/admin/Login.vue
git commit -m "feat: add admin login page"
```

---

## Task 3: `tool_events` 集計とAdminダッシュボードAPI

**Files:**
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/DashboardControllerTest.php`

**Interfaces:**
- Produces:
  - `GET /admin/dashboard` → ルート名 `admin.dashboard`。`auth:admin` ミドルウェア。Inertiaページ `admin/Dashboard` を以下の `stats` propsでレンダリング:
    ```json
    {
      "stats": [
        {
          "slug": "image-compress",
          "name": "画像圧縮",
          "total_executions": 12,
          "total_files": 18,
          "last_7_days": 3,
          "last_30_days": 12
        }
      ]
    }
    ```
    `config('tools')` の順序どおりに1ツール1レコード。データが無いツールは全て `0`。

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/DashboardControllerTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\ToolEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function actingAsAdmin(): Admin
{
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    test()->actingAs($admin, 'admin');

    return $admin;
}

test('unauthenticated visitors are redirected to the admin login page', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
});

test('the dashboard renders zeroed stats when there is no data', function () {
    actingAsAdmin();

    $response = $this->get(route('admin.dashboard'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Dashboard')
        ->has('stats', 1)
        ->where('stats.0.slug', 'image-compress')
        ->where('stats.0.total_executions', 0)
        ->where('stats.0.total_files', 0)
        ->where('stats.0.last_7_days', 0)
        ->where('stats.0.last_30_days', 0)
    );
});

test('the dashboard aggregates tool_events correctly', function () {
    actingAsAdmin();

    ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'process',
        'file_count' => 2,
    ])->forceFill(['created_at' => Carbon::now()->subDays(2)])->save();

    ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'process',
        'file_count' => 3,
    ])->forceFill(['created_at' => Carbon::now()->subDays(20)])->save();

    ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'process',
        'file_count' => 1,
    ])->forceFill(['created_at' => Carbon::now()->subDays(40)])->save();

    ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'upload',
        'file_count' => 5,
    ]);

    $response = $this->get(route('admin.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Dashboard')
        ->where('stats.0.total_executions', 3)
        ->where('stats.0.total_files', 6)
        ->where('stats.0.last_7_days', 1)
        ->where('stats.0.last_30_days', 2)
    );
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=DashboardControllerTest`
Expected: FAIL(ルート `admin.dashboard` が存在しない)

- [ ] **Step 3: コントローラーを実装**

`app/Http/Controllers/Admin/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ToolEvent;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = collect(config('tools'))
            ->map(function (array $tool) {
                // A closure that returns a fresh Builder instance each call
                // (Eloquent's ::query() always builds a new one), so no
                // clone() is needed between the independent aggregate calls
                // below.
                $baseQuery = fn () => ToolEvent::query()
                    ->where('tool_slug', $tool['slug'])
                    ->where('event_type', 'process');

                return [
                    'slug' => $tool['slug'],
                    'name' => $tool['name'],
                    'total_executions' => $baseQuery()->count(),
                    'total_files' => (int) $baseQuery()->sum('file_count'),
                    'last_7_days' => $baseQuery()->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
                    'last_30_days' => $baseQuery()->where('created_at', '>=', Carbon::now()->subDays(30))->count(),
                ];
            })
            ->values();

        return Inertia::render('admin/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
```

- [ ] **Step 4: ルートを追加**

`routes/admin.php` の `auth:admin` グループに追記(`use` 文にも `DashboardController` を追加):

```php
use App\Http\Controllers\Admin\DashboardController;
```

```php
    Route::middleware('auth:admin')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
```

- [ ] **Step 5: テストを再実行**

Run: `php artisan test --filter=DashboardControllerTest`
Expected: PASS(3 tests)

- [ ] **Step 6: コミット**

```bash
git add app/Http/Controllers/Admin/DashboardController.php routes/admin.php tests/Feature/Admin/DashboardControllerTest.php
git commit -m "feat: add admin dashboard tool_events aggregation"
```

---

## Task 4: Adminダッシュボードページ(フロントエンド)+ 画面確認

**Files:**
- Create: `resources/js/pages/admin/Dashboard.vue`

**Interfaces:**
- Consumes: `GET /admin/dashboard` の `stats` props(Task 3)

- [ ] **Step 1: ページコンポーネントを実装**

`resources/js/pages/admin/Dashboard.vue`:

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

interface ToolStat {
    slug: string;
    name: string;
    total_executions: number;
    total_files: number;
    last_7_days: number;
    last_30_days: number;
}

defineProps<{ stats: ToolStat[] }>();

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <Head title="Admin ダッシュボード" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-6">
                <h1 class="text-lg font-bold text-gray-900">Admin ダッシュボード</h1>
                <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="logout">ログアウト</button>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-10">
            <div v-if="stats.length === 0" class="text-sm text-gray-500">データがありません。</div>

            <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">ツール名</th>
                            <th class="px-4 py-3 font-medium">実行回数</th>
                            <th class="px-4 py-3 font-medium">ダウンロード回数(概算)</th>
                            <th class="px-4 py-3 font-medium">直近7日</th>
                            <th class="px-4 py-3 font-medium">直近30日</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="stat in stats" :key="stat.slug" class="border-b border-gray-100 last:border-0">
                            <td class="px-4 py-3 text-gray-900">{{ stat.name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ stat.total_executions }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ stat.total_files }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ stat.last_7_days }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ stat.last_30_days }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 2: 型・Lintチェック**

Run: `npx vue-tsc --noEmit && npm run lint:check`
Expected: エラーなし

- [ ] **Step 3: コミット**

```bash
git add resources/js/pages/admin/Dashboard.vue
git commit -m "feat: add admin dashboard page"
```

- [ ] **Step 4: 実際の画面をPlaywrightで確認する(ユーザーへスクリーンショット送付)**

このステップはコード変更を伴わない、視覚的な動作確認。Phase 1と同様、コントローラー(このセッション)が personally 実施する — 実装タスクの一部として implementer に委譲しない。

1. ローカルDBに `php artisan admin:create "Test Admin" admin@example.com password1234` でテスト用Adminアカウントを作成する。
2. `npm run build` を実行し、本番ビルドが通ることを確認する。
3. `php artisan serve` + `vite` でローカルサーバーを起動する。
4. Playwrightで以下を確認しスクリーンショットを撮る:
   - `/admin/dashboard` に未ログインでアクセスし、`/admin/login` にリダイレクトされることを確認
   - `/admin/login` のフォーム表示を確認しスクリーンショット
   - 間違ったパスワードでログインを試み、エラーメッセージが表示されることを確認
   - 正しい認証情報でログインし、`/admin/dashboard` に遷移、集計テーブルが表示されることを確認しスクリーンショット(データがある状態・無い状態の両方、可能なら)
   - ログアウトボタンで `/admin/login` に戻ることを確認
5. スクリーンショットをユーザーに送付し、UIの見た目について確認を取る。
6. 問題があれば該当ファイルを修正し、Step 2 のチェックを再実行してから再度コミットする。

---

## 完了確認(Phase 2 全体)

- [ ] `composer test`(Pint + PHPStan + Pest)がグリーン
- [ ] `npm run lint:check && npm run format:check && npm run types:check` がグリーン
- [ ] `npm run build` が成功する
- [ ] Playwrightでの実画面確認(Task 4 Step 4)をユーザーに提示し、フィードバックを反映済み
- [ ] TASKS.md の Phase 2 チェックリストを更新する
