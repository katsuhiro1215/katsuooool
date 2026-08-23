# Premium/Stripe連携 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** katsuoooolに月額¥600/年額¥6,600のPremiumサブスクリプションを追加し、画像系ツールのバッチ件数上限・レート制限・マイページ(利用履歴)の3軸でPremium会員に価値を提供する。

**Architecture:** Laravel Cashier + Stripe Checkout(ホスト型決済画面)+ Stripe Billing Portal(ホスト型管理画面)。サブスクリプション状態は独自カラムを持たず、Cashierの`subscriptions`テーブルと`$user->subscribed('default')`で判定する。

**Tech Stack:** Laravel 13 / Laravel Cashier(Stripe SDK) / Inertia.js v2 / Vue 3 / TypeScript / Pest

**Spec:** [docs/superpowers/specs/2026-08-23-premium-stripe-design.md](../specs/2026-08-23-premium-stripe-design.md)

## Global Constraints

- 月額¥600・年額¥6,600、税込固定価格(Stripe Taxは使わない)
- 無料トライアルなし、返金なし、解約は支払い済み期間の終了まで利用可(`cancel_at_period_end`相当。Cashierの`subscribed()`はグレースピリオド中も`true`を返すため追加実装不要)
- 独自の`is_premium`カラムは追加しない。判定は必ず`$user->subscribed('default')`(または本プランで定義する`$user->isPremium()`ラッパー)を通す
- 既存の画像系ツールを有料の壁の中に入れない(無料利用は維持し、バッチ件数上限とレート制限のみ差をつける)
- `.env`の既存キー名(`STRIPE_PUBLIC_KEY`・`STRIPE_SECRET_KEY`)は変更しない。Cashier側の設定でこれらを参照する
- 各タスック完了時に `composer ci:check`(Pint/PHPStan/Pest/ESLint/Prettier/vue-tsc/Vitest)がグリーンであること

---

## Task 1: Laravel Cashierの導入とUserモデルへの統合

**Files:**
- Modify: `composer.json`(`laravel/cashier`を追加)
- Modify: `app/Models/User.php`
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_add_cashier_columns_to_users_table.php`(Cashierのvendor:publishで生成)
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_subscriptions_table.php`(同上)
- Create: `database/migrations/xxxx_xx_xx_xxxxxx_create_subscription_items_table.php`(同上)
- Modify: `config/cashier.php`(vendor:publishで生成後、key/secretのマッピングを編集)
- Test: `tests/Feature/Premium/UserIsPremiumTest.php`

**Interfaces:**
- Produces: `User::isPremium(): bool`(以降の全タスクがこのメソッドでPremium判定を行う)

- [ ] **Step 1: Cashierをインストールする**

```bash
composer require laravel/cashier
```

- [ ] **Step 2: Cashierの設定・マイグレーションを publish する**

```bash
php artisan vendor:publish --tag="cashier-config"
php artisan vendor:publish --tag="cashier-migrations"
```

- [ ] **Step 3: 生成されたマイグレーションファイルを確認する**

```bash
ls database/migrations/ | grep -i cashier
cat database/migrations/*_create_subscriptions_table.php
```

`subscriptions`テーブルに`user_id`・`type`・`stripe_id`・`stripe_status`・`stripe_price`・`quantity`・`trial_ends_at`・`ends_at`のカラムがあることを確認する(Cashierの標準スキーマ)。カラム名が異なる場合は、以降のタスクのコード中の該当カラム名をここで確認した実際の名前に読み替えること。

- [ ] **Step 4: `config/cashier.php`の`key`/`secret`を既存の`.env`変数名にマッピングする**

`config/cashier.php`内の該当箇所を以下のように編集する:

```php
'key' => env('STRIPE_PUBLIC_KEY'),

'secret' => env('STRIPE_SECRET_KEY'),
```

- [ ] **Step 5: `User`モデルに`Billable`トレイトと`isPremium()`を追加する**

`app/Models/User.php`の`use`宣言に追加:

```php
use Laravel\Cashier\Billable;
```

`class User`内の`use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;`を以下に変更:

```php
use Billable, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
```

クラス内に以下のメソッドを追加する:

```php
public function isPremium(): bool
{
    return $this->subscribed('default');
}
```

- [ ] **Step 6: マイグレーションを実行する**

```bash
php artisan migrate
```

- [ ] **Step 7: 失敗するテストを書く**

`tests/Feature/Premium/UserIsPremiumTest.php`を新規作成:

```php
<?php

use App\Models\User;

test('a user with no subscription is not premium', function () {
    $user = User::factory()->create();

    expect($user->isPremium())->toBeFalse();
});

test('a user with an active default subscription is premium', function () {
    $user = User::factory()->create();

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    expect($user->isPremium())->toBeTrue();
});

test('a user whose subscription has fully ended is not premium', function () {
    $user = User::factory()->create();

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test456',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
        'ends_at' => now()->subDay(),
    ]);

    expect($user->isPremium())->toBeFalse();
});
```

- [ ] **Step 8: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Premium/UserIsPremiumTest.php`
Expected: 3 tests PASS(Cashierの`valid()`ロジックが`stripe_status`と`ends_at`を正しく評価するため、追加実装なしで通るはず)

- [ ] **Step 9: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 10: コミットする**

```bash
git add composer.json composer.lock app/Models/User.php config/cashier.php database/migrations tests/Feature/Premium/UserIsPremiumTest.php
git commit -m "feat: install Laravel Cashier and add User::isPremium()"
```

---

## Task 2: Premium料金ページ(`/premium`)

**Files:**
- Create: `app/Http/Controllers/PremiumController.php`
- Create: `resources/js/pages/Premium.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/Premium/PremiumPageTest.php`

**Interfaces:**
- Consumes: `User::isPremium()`(Task 1)
- Produces: ルート名`premium.index`。Vueページ`Premium`は`isPremium: boolean`propを受け取る

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Premium/PremiumPageTest.php`を新規作成:

```php
<?php

use App\Models\User;

test('the premium page renders for guests', function () {
    $response = $this->get(route('premium.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Premium')
        ->where('isPremium', false)
    );
});

test('the premium page reports true for an already-premium user', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test789',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('premium.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Premium')
        ->where('isPremium', true)
    );
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Premium/PremiumPageTest.php`
Expected: FAIL(ルート`premium.index`が存在しない)

- [ ] **Step 3: `PremiumController`を作成する**

`app/Http/Controllers/PremiumController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PremiumController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Premium', [
            'isPremium' => (bool) $request->user()?->isPremium(),
        ]);
    }
}
```

- [ ] **Step 4: ルートを追加する**

`routes/web.php`の先頭付近、`use`宣言に追加:

```php
use App\Http\Controllers\PremiumController;
```

`require __DIR__.'/settings.php';`の直前に追加:

```php
Route::get('/premium', [PremiumController::class, 'index'])->name('premium.index');
```

- [ ] **Step 5: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Premium/PremiumPageTest.php`
Expected: 2 tests PASS(Inertiaのコンポーネントファイルがまだ無いため、実際にはこの時点でも「component file does not exist」で失敗する。Step 6でVueページを作成後に再実行する)

- [ ] **Step 6: `Premium.vue`を作成する**

`resources/js/pages/Premium.vue`:

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ isPremium: boolean }>();

const PLANS = [
    {
        key: 'monthly',
        label: '月額プラン',
        price: '¥600',
        period: '/ 月',
        note: '',
    },
    {
        key: 'yearly',
        label: '年額プラン',
        price: '¥6,600',
        period: '/ 年',
        note: '月額換算で1ヶ月分お得',
    },
];
</script>

<template>
    <Head title="Premium - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">Premium</h1>
                <p class="mt-2 text-sm text-gray-600">
                    画像系ツールのバッチ処理件数・レート制限が緩和され、ツール利用履歴を確認できるマイページが使えるようになります。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                v-if="isPremium"
                class="mb-8 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700"
            >
                すでにPremiumにご登録いただいています。
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                    v-for="plan in PLANS"
                    :key="plan.key"
                    class="rounded-lg border border-gray-200 bg-white p-6"
                >
                    <h2 class="text-sm font-medium text-gray-700">
                        {{ plan.label }}
                    </h2>
                    <p class="mt-2 text-3xl font-bold text-gray-900">
                        {{ plan.price }}
                        <span class="text-sm font-normal text-gray-500">{{
                            plan.period
                        }}</span>
                    </p>
                    <p v-if="plan.note" class="mt-1 text-xs text-gray-500">
                        {{ plan.note }}
                    </p>
                    <Link
                        v-if="!isPremium"
                        :href="route('premium.checkout', { plan: plan.key })"
                        method="post"
                        as="button"
                        class="mt-4 block w-full rounded-md bg-gray-900 px-4 py-2 text-center text-sm font-medium text-white hover:bg-gray-700"
                    >
                        このプランで登録
                    </Link>
                </div>
            </div>

            <p class="mt-8 text-xs text-gray-500">
                価格は税込です。無料トライアルはありません。ご登録と同時に課金が開始されます。返金は行っておりません。解約はいつでも可能で、解約後もお支払い済みの期間終了までPremium機能をご利用いただけます。
            </p>
        </main>
    </div>
</template>
```

- [ ] **Step 7: テストを再実行して通ることを確認する**

Run: `php artisan test tests/Feature/Premium/PremiumPageTest.php`
Expected: 2 tests PASS

- [ ] **Step 8: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 9: コミットする**

```bash
git add app/Http/Controllers/PremiumController.php resources/js/pages/Premium.vue routes/web.php tests/Feature/Premium/PremiumPageTest.php
git commit -m "feat: add Premium pricing page"
```

---

## Task 3: Checkoutフロー(登録導線)

**Files:**
- Modify: `app/Http/Controllers/PremiumController.php`
- Modify: `routes/web.php`
- Modify: `.env`・`.env.example`(価格ID用の変数を追加)
- Test: `tests/Feature/Premium/CheckoutTest.php`

**Interfaces:**
- Consumes: `User::isPremium()`(Task 1)
- Produces: ルート名`premium.checkout`(POST)・`premium.thanks`(GET)

Stripeダッシュボード(開発用アカウント)で月額¥600・年額¥6,600の商品と価格を作成し、それぞれのPrice IDを`.env`に設定する必要がある(ユーザー側の作業)。本タスクでは`.env.example`にプレースホルダーの変数名のみ追加し、実際の値はユーザーが設定する。

- [ ] **Step 1: `.env.example`に価格ID用の変数を追加する**

`.env.example`の末尾に追加:

```
STRIPE_PRICE_MONTHLY=
STRIPE_PRICE_YEARLY=
```

- [ ] **Step 2: 失敗するテストを書く**

`tests/Feature/Premium/CheckoutTest.php`を新規作成:

```php
<?php

use App\Models\User;

test('guests are redirected to login when starting checkout', function () {
    $response = $this->post(route('premium.checkout', ['plan' => 'monthly']));

    $response->assertRedirect(route('login'));
});

test('an invalid plan is rejected with a validation error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('premium.checkout', ['plan' => 'lifetime']));

    $response->assertStatus(422);
});

test('an already-premium user is redirected back to the premium page instead of checking out again', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test999',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->post(route('premium.checkout', ['plan' => 'monthly']));

    $response->assertRedirect(route('premium.index'));
});
```

- [ ] **Step 3: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Premium/CheckoutTest.php`
Expected: FAIL(ルート`premium.checkout`が存在しない)

- [ ] **Step 4: `PremiumController`に`checkout()`と`thanks()`を追加する**

`app/Http/Controllers/PremiumController.php`を以下の内容に更新(既存の`index()`はそのまま残す):

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Checkout;

class PremiumController extends Controller
{
    private const PLAN_PRICE_ENV_KEYS = [
        'monthly' => 'STRIPE_PRICE_MONTHLY',
        'yearly' => 'STRIPE_PRICE_YEARLY',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Premium', [
            'isPremium' => (bool) $request->user()?->isPremium(),
        ]);
    }

    public function checkout(Request $request): RedirectResponse|Checkout
    {
        $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', array_keys(self::PLAN_PRICE_ENV_KEYS))],
        ]);

        $user = $request->user();

        if ($user->isPremium()) {
            return Redirect::route('premium.index');
        }

        $plan = $request->string('plan')->toString();
        $priceId = (string) env(self::PLAN_PRICE_ENV_KEYS[$plan]);

        return $user->newSubscription('default', $priceId)->checkout([
            'success_url' => route('premium.thanks'),
            'cancel_url' => route('premium.index'),
        ]);
    }

    public function thanks(): Response
    {
        return Inertia::render('PremiumThanks');
    }
}
```

- [ ] **Step 5: ルートを追加する**

`routes/web.php`の`Route::get('/premium', ...)`の直後に追加:

```php
Route::post('/premium/checkout', [PremiumController::class, 'checkout'])
    ->middleware('auth')
    ->name('premium.checkout');

Route::get('/premium/thanks', [PremiumController::class, 'thanks'])
    ->middleware('auth')
    ->name('premium.thanks');
```

- [ ] **Step 6: `PremiumThanks.vue`を作成する**

`resources/js/pages/PremiumThanks.vue`:

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="ご登録ありがとうございます - katsuooool" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div class="max-w-md text-center">
            <h1 class="text-2xl font-bold text-gray-900">
                Premiumへのご登録ありがとうございます
            </h1>
            <p class="mt-4 text-sm text-gray-600">
                決済が完了しました。反映まで数秒かかる場合があります。
            </p>
            <Link
                href="/"
                class="mt-6 inline-block rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
            >
                トップに戻る
            </Link>
        </div>
    </div>
</template>
```

- [ ] **Step 7: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Premium/CheckoutTest.php`
Expected: 3 tests PASS(3つのテストはStripe APIを実際には呼び出さない分岐のみを検証しており、正常系のCheckoutセッション作成は本タスクではテストしない。実際のCheckout遷移はTask 9のPlaywright確認で、Stripeテストモードのキーを使って手動確認する)

- [ ] **Step 8: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 9: コミットする**

```bash
git add app/Http/Controllers/PremiumController.php resources/js/pages/PremiumThanks.vue routes/web.php .env.example tests/Feature/Premium/CheckoutTest.php
git commit -m "feat: add Stripe Checkout flow for Premium subscriptions"
```

---

## Task 4: Stripe Webhookエンドポイントの確認

**Files:**
- Modify: `routes/web.php`(必要な場合のみ)

**Interfaces:**
- Consumes: なし
- Produces: `POST /stripe/webhook`ルート(Cashierの`WebhookController`が処理し、`subscriptions`テーブルを更新する)

- [ ] **Step 1: Cashierがwebhookルートを自動登録しているか確認する**

```bash
php artisan route:list | grep stripe
```

- [ ] **Step 2: ルートが存在しない場合のみ、手動で追加する**

`POST /stripe/webhook`が上記コマンドの出力に含まれていない場合、`routes/web.php`の`require __DIR__.'/settings.php';`の直前に追加する:

```php
Route::post('/stripe/webhook', [\Laravel\Cashier\Http\Controllers\WebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
```

- [ ] **Step 3: CSRF除外の確認**

`bootstrap/app.php`の`->withMiddleware()`内に、`/stripe/webhook`をCSRF検証から除外する設定が必要か確認する。Cashierは通常このルートを自動でCSRF除外扱いにするが、Step 2で手動追加した場合は以下を`withMiddleware`のクロージャ内に追加する:

```php
$middleware->validateCsrfTokens(except: [
    'stripe/webhook',
]);
```

- [ ] **Step 4: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 5: コミットする(変更があった場合のみ)**

```bash
git add routes/web.php bootstrap/app.php
git commit -m "feat: ensure Stripe webhook endpoint is registered"
```

変更がなければこのタスクはコミット不要(確認のみで完了)。

---

## Task 5: Billing Portalへのリンク(プラン変更・解約)

**Files:**
- Modify: `app/Http/Controllers/PremiumController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Premium/BillingPortalTest.php`

**Interfaces:**
- Consumes: `User::isPremium()`(Task 1)
- Produces: ルート名`premium.billing-portal`(POST、Premium会員のみ)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Premium/BillingPortalTest.php`を新規作成:

```php
<?php

use App\Models\User;

test('guests are redirected to login', function () {
    $response = $this->post(route('premium.billing-portal'));

    $response->assertRedirect(route('login'));
});

test('a non-premium user cannot open the billing portal', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('premium.billing-portal'));

    $response->assertForbidden();
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Premium/BillingPortalTest.php`
Expected: FAIL(ルートが存在しない)

- [ ] **Step 3: `PremiumController`に`billingPortal()`を追加する**

`app/Http/Controllers/PremiumController.php`の`thanks()`メソッドの後に追加:

```php
public function billingPortal(Request $request): RedirectResponse
{
    $user = $request->user();

    abort_unless($user->isPremium(), 403);

    return $user->redirectToBillingPortal(route('premium.index'));
}
```

- [ ] **Step 4: ルートを追加する**

`routes/web.php`の`Route::get('/premium/thanks', ...)`の直後に追加:

```php
Route::post('/premium/billing-portal', [PremiumController::class, 'billingPortal'])
    ->middleware('auth')
    ->name('premium.billing-portal');
```

- [ ] **Step 5: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Premium/BillingPortalTest.php`
Expected: 2 tests PASS

- [ ] **Step 6: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 7: コミットする**

```bash
git add app/Http/Controllers/PremiumController.php routes/web.php tests/Feature/Premium/BillingPortalTest.php
git commit -m "feat: add Stripe Billing Portal redirect for Premium members"
```

---

## Task 6: 画像系ツールのバッチ件数上限をPremiumで切り替える

**Files:**
- Modify: `app/Http/Controllers/Tools/ImageCompressController.php`
- Modify: `app/Http/Controllers/Tools/ImageConvertController.php`
- Modify: `app/Http/Controllers/Tools/ImageResizeController.php`
- Modify: `app/Http/Controllers/Tools/ImageRotateController.php`
- Modify: `app/Http/Controllers/Tools/ImageColorExtractController.php`
- Modify: `app/Http/Controllers/Tools/OgpImageController.php`
- Test: `tests/Feature/Tools/ImageCompressControllerTest.php`(既存ファイルに追記)

**Interfaces:**
- Consumes: `User::isPremium()`(Task 1)

6つのコントローラーはすべて同じパターン(`private const MAX_FILE_COUNT = 10;`を`$uploadService->store()`呼び出しの`maxFileCount`引数に渡している)。ここでは`ImageCompressController`を例に手順を示し、残り5ファイルにも同じ変更を適用する。

- [ ] **Step 1: `ImageCompressControllerTest.php`に失敗するテストを追記する**

`tests/Feature/Tools/ImageCompressControllerTest.php`の末尾に追加:

```php
test('a free user cannot upload more than 3 files at once', function () {
    $user = User::factory()->create();
    $files = collect(range(1, 4))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files]);

    $response->assertStatus(422);
});

test('a premium user can upload up to 10 files at once', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_compress',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 4))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(4, 'results');
});
```

`use App\Models\User;`が既にファイル先頭にない場合は追加する。

- [ ] **Step 2: テストを実行して1つ目が失敗することを確認する**

Run: `php artisan test tests/Feature/Tools/ImageCompressControllerTest.php`
Expected: `a free user cannot upload more than 3 files at once` が FAIL(現状は10件まで許可されているため)

- [ ] **Step 3: `ImageCompressController`のバリデーションとアップロード上限を動的にする**

`app/Http/Controllers/Tools/ImageCompressController.php`の`process()`冒頭を以下のように変更する:

```php
public function process(
    Request $request,
    ToolUploadService $uploadService,
    ImageCompressService $compressService,
    ToolDownloadService $downloadService,
    ToolEventLogger $eventLogger,
): JsonResponse {
    $maxFileCount = $request->user()?->isPremium() ? self::MAX_FILE_COUNT : self::FREE_MAX_FILE_COUNT;

    $request->validate([
        'files' => ['required', 'array', 'max:'.$maxFileCount],
        'files.*' => ['file'],
    ]);
```

クラス冒頭の定数群に追加する:

```php
private const FREE_MAX_FILE_COUNT = 3;
```

`$uploadService->store()`呼び出しの`maxFileCount: self::MAX_FILE_COUNT,`を`maxFileCount: $maxFileCount,`に変更する。

- [ ] **Step 4: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Tools/ImageCompressControllerTest.php`
Expected: 全テストPASS

- [ ] **Step 5: 残り5コントローラーに同じ変更を適用する**

`ImageConvertController`・`ImageResizeController`・`ImageRotateController`・`ImageColorExtractController`・`OgpImageController`それぞれに対して、Step 3と同じ変更(`FREE_MAX_FILE_COUNT = 3`定数の追加、`process()`冒頭での`$maxFileCount`算出、バリデーションと`store()`呼び出しへの反映)を行う。各ファイルの対応するControllerTest(`ImageConvertControllerTest.php`等)にもStep 1と同内容のテスト(ツール名とルート名のみ差し替え)を追記する。

- [ ] **Step 6: 全体のテストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Tools/`
Expected: 全テストPASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add app/Http/Controllers/Tools/ImageCompressController.php app/Http/Controllers/Tools/ImageConvertController.php app/Http/Controllers/Tools/ImageResizeController.php app/Http/Controllers/Tools/ImageRotateController.php app/Http/Controllers/Tools/ImageColorExtractController.php app/Http/Controllers/Tools/OgpImageController.php tests/Feature/Tools/
git commit -m "feat: raise batch file count limit for Premium users on image tools"
```

---

## Task 7: 画像系ツールのレート制限をPremiumで緩和する

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Premium/ImageToolRateLimitTest.php`

**Interfaces:**
- Consumes: `User::isPremium()`(Task 1)
- Produces: 名前付きレートリミッター`image-tools`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Premium/ImageToolRateLimitTest.php`を新規作成:

```php
<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

test('a free user is rate-limited after 10 requests per minute', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('image-compress.process'), ['files' => [$file]]);
    }

    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => [$file]]);

    $response->assertStatus(429);
});

test('a premium user is not rate-limited until 30 requests per minute', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_ratelimit',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    for ($i = 0; $i < 15; $i++) {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $response = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('image-compress.process'), ['files' => [$file]]);

        expect($response->status())->not->toBe(429);
    }
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/Premium/ImageToolRateLimitTest.php`
Expected: 1つ目のテストはPASS(既存の`throttle:10,1`がIP単位で効くため)、2つ目は15回目までに429が発生してFAIL

- [ ] **Step 3: `AppServiceProvider`に`image-tools`リミッターを定義する**

`app/Providers/AppServiceProvider.php`の`boot()`内、既存の`RateLimiter::for('admin-login', ...)`の後に追加:

```php
RateLimiter::for('image-tools', function (Request $request) {
    $isPremium = (bool) $request->user()?->isPremium();
    $perMinute = $isPremium ? 30 : 10;
    $key = $request->user()?->id ?? $request->ip();

    return Limit::perMinute($perMinute)->by($key);
});
```

- [ ] **Step 4: ルート定義の`throttle:10,1`を`throttle:image-tools`に置き換える**

`routes/web.php`内、以下8つのPOSTルートに付いている`->middleware('throttle:10,1')`を`->middleware('throttle:image-tools')`に置き換える:

- `image-compress.process`
- `image-convert.process`
- `image-resize.process`
- `image-color-extract.process`
- `image-rotate.process`
- `image-crop.process`
- `ogp-image.process`
- `favicon-generate.process`

`grep -n "throttle:10,1" routes/web.php`で対象行を特定できる。

- [ ] **Step 5: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/Premium/ImageToolRateLimitTest.php`
Expected: 2 tests PASS

- [ ] **Step 6: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 7: コミットする**

```bash
git add app/Providers/AppServiceProvider.php routes/web.php tests/Feature/Premium/ImageToolRateLimitTest.php
git commit -m "feat: relax image-tools rate limit for Premium users"
```

---

## Task 8: マイページ(利用履歴、Premium限定)

**Files:**
- Create: `app/Http/Middleware/EnsurePremium.php`
- Create: `app/Http/Controllers/MyPageController.php`
- Create: `resources/js/pages/MyPage.vue`
- Modify: `bootstrap/app.php`(`premium`ミドルウェアエイリアスの登録)
- Modify: `routes/web.php`
- Test: `tests/Feature/MyPageControllerTest.php`

**Interfaces:**
- Consumes: `User::isPremium()`(Task 1)
- Produces: ルート名`mypage.index`

- [ ] **Step 1: `EnsurePremium`ミドルウェアを作成する**

```bash
php artisan make:middleware EnsurePremium
```

`app/Http/Middleware/EnsurePremium.php`の中身を以下に置き換える:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremium
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (! $request->user()?->isPremium()) {
            return redirect()->route('premium.index');
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: `bootstrap/app.php`に`premium`ミドルウェアエイリアスを登録する**

`use`宣言に追加:

```php
use App\Http\Middleware\EnsurePremium;
```

`->withMiddleware(function (Middleware $middleware): void {`の直後に追加:

```php
$middleware->alias(['premium' => EnsurePremium::class]);
```

- [ ] **Step 3: 失敗するテストを書く**

`tests/Feature/MyPageControllerTest.php`を新規作成:

```php
<?php

use App\Models\ToolEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $response = $this->get(route('mypage.index'));

    $response->assertRedirect(route('login'));
});

test('a non-premium logged-in user is redirected to the premium page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('mypage.index'));

    $response->assertRedirect(route('premium.index'));
});

test('a premium user sees only their own tool history', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_mypage',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $otherUser = User::factory()->create();

    ToolEvent::create(['user_id' => $user->id, 'tool_slug' => 'image-crop', 'event_type' => 'process']);
    ToolEvent::create(['user_id' => $otherUser->id, 'tool_slug' => 'image-crop', 'event_type' => 'process']);

    $response = $this->actingAs($user)->get(route('mypage.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('MyPage')
        ->has('toolEvents.data', 1)
        ->where('toolEvents.data.0.tool_slug', 'image-crop')
    );
});
```

- [ ] **Step 4: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/MyPageControllerTest.php`
Expected: FAIL(ルート`mypage.index`が存在しない)

- [ ] **Step 5: `MyPageController`を作成する**

`app/Http/Controllers/MyPageController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ToolEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyPageController extends Controller
{
    public function index(Request $request): Response
    {
        $toolEvents = ToolEvent::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('MyPage', [
            'toolEvents' => $toolEvents,
        ]);
    }
}
```

- [ ] **Step 6: ルートを追加する**

`routes/web.php`の`use`宣言に追加:

```php
use App\Http\Controllers\MyPageController;
```

`Route::post('/premium/billing-portal', ...)`の直後に追加:

```php
Route::get('/mypage', [MyPageController::class, 'index'])
    ->middleware(['auth', 'premium'])
    ->name('mypage.index');
```

- [ ] **Step 7: `MyPage.vue`を作成する**

`resources/js/pages/MyPage.vue`:

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

interface ToolEventRow {
    id: number;
    tool_slug: string;
    event_type: string;
    created_at: string | null;
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

defineProps<{ toolEvents: Paginated<ToolEventRow> }>();

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString('ja-JP') : '-';
}

function openBillingPortal() {
    router.post(route('premium.billing-portal'));
}
</script>

<template>
    <Head title="マイページ - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <div class="mt-2 flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900">
                        マイページ
                    </h1>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        @click="openBillingPortal"
                    >
                        請求ポータルを開く
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <h2 class="mb-4 text-sm font-medium text-gray-700">
                ツール利用履歴
            </h2>

            <div
                v-if="toolEvents.data.length === 0"
                class="text-sm text-gray-500"
            >
                利用履歴がありません。
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
                            <th class="px-4 py-3 font-medium">ツール</th>
                            <th class="px-4 py-3 font-medium">種別</th>
                            <th class="px-4 py-3 font-medium">日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="event in toolEvents.data"
                            :key="event.id"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <td class="px-4 py-3 text-gray-900">
                                {{ event.tool_slug }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ event.event_type }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ formatDate(event.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="toolEvents.links.length > 3"
                class="mt-4 flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in toolEvents.links"
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
    </div>
</template>
```

- [ ] **Step 8: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/MyPageControllerTest.php`
Expected: 3 tests PASS

- [ ] **Step 9: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 10: コミットする**

```bash
git add app/Http/Middleware/EnsurePremium.php app/Http/Controllers/MyPageController.php resources/js/pages/MyPage.vue bootstrap/app.php routes/web.php tests/Feature/MyPageControllerTest.php
git commit -m "feat: add Premium-only mypage with tool usage history"
```

---

## Task 9: 特定商取引法ページの雛形

**Files:**
- Create: `app/Http/Controllers/LegalController.php`
- Create: `resources/js/pages/legal/Tokushoho.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/LegalControllerTest.php`

**Interfaces:**
- Produces: ルート名`legal.tokushoho`

このページの内容(事業者名・所在地・連絡先等)はユーザー(事業者)からの情報提供待ち。本タスクでは、法律上必須の項目をラベルとして用意した雛形ページを作成し、値の部分に分かりやすいプレースホルダーを入れておく。実際のデプロイ前に、ユーザーが`resources/js/pages/legal/Tokushoho.vue`内の値を実際の情報に差し替える。

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/LegalControllerTest.php`を新規作成:

```php
<?php

test('the tokushoho page renders', function () {
    $response = $this->get(route('legal.tokushoho'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('legal/Tokushoho'));
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `php artisan test tests/Feature/LegalControllerTest.php`
Expected: FAIL(ルートが存在しない)

- [ ] **Step 3: `LegalController`を作成する**

`app/Http/Controllers/LegalController.php`:

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function tokushoho(): Response
    {
        return Inertia::render('legal/Tokushoho');
    }
}
```

- [ ] **Step 4: ルートを追加する**

`routes/web.php`の`use`宣言に追加:

```php
use App\Http\Controllers\LegalController;
```

`Route::get('/mypage', ...)`の直後に追加:

```php
Route::get('/legal/tokushoho', [LegalController::class, 'tokushoho'])->name('legal.tokushoho');
```

- [ ] **Step 5: `Tokushoho.vue`を作成する**

`resources/js/pages/legal/Tokushoho.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface LegalItem {
    label: string;
    value: string;
}

const ITEMS: LegalItem[] = [
    { label: '販売事業者', value: '【事業者名を入力してください】' },
    { label: '運営責任者', value: '【運営責任者名を入力してください】' },
    { label: '所在地', value: '【所在地を入力してください】' },
    { label: '連絡先', value: '【メールアドレス等の連絡先を入力してください】' },
    { label: '販売価格', value: '月額¥600(税込)・年額¥6,600(税込)' },
    { label: '商品代金以外の必要料金', value: 'なし' },
    { label: 'お支払い方法', value: 'クレジットカード決済(Stripe)' },
    { label: 'お支払い時期', value: 'ご登録時に即時課金。以降、選択したプランの周期ごとに自動更新' },
    { label: 'サービス提供時期', value: '決済完了後、即時利用可能' },
    {
        label: '返品・キャンセルについて',
        value:
            'デジタルサービスの性質上、返金は行っておりません。解約はいつでも可能で、解約後もお支払い済みの期間終了まではサービスをご利用いただけます。',
    },
];
</script>

<template>
    <Head title="特定商取引法に基づく表記 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    特定商取引法に基づく表記
                </h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <dl class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
                <div
                    v-for="item in ITEMS"
                    :key="item.label"
                    class="grid grid-cols-1 gap-1 px-4 py-4 sm:grid-cols-3 sm:gap-4"
                >
                    <dt class="text-sm font-medium text-gray-500">
                        {{ item.label }}
                    </dt>
                    <dd class="text-sm text-gray-900 sm:col-span-2">
                        {{ item.value }}
                    </dd>
                </div>
            </dl>
        </main>
    </div>
</template>
```

- [ ] **Step 6: テストを実行して通ることを確認する**

Run: `php artisan test tests/Feature/LegalControllerTest.php`
Expected: 1 test PASS

- [ ] **Step 7: `composer ci:check`を実行してグリーンを確認する**

- [ ] **Step 8: コミットする**

```bash
git add app/Http/Controllers/LegalController.php resources/js/pages/legal/Tokushoho.vue routes/web.php tests/Feature/LegalControllerTest.php
git commit -m "feat: add tokushoho (specified commercial transactions) page scaffold"
```

**重要:** マージ後、デプロイ前に`resources/js/pages/legal/Tokushoho.vue`内の`【...を入力してください】`のプレースホルダーを実際の事業者情報に差し替えること。

---

## Task 10: 全体の最終確認とドキュメント更新

**Files:**
- Modify: `TASKS.md`
- Modify: `SPEC.md`

**Interfaces:**
- Consumes: なし(Task 1〜9の完了を前提とする)

- [ ] **Step 1: 全体のテストスイートを実行する**

Run: `composer ci:check`
Expected: 全項目(Pint/PHPStan/Pest/ESLint/Prettier/vue-tsc/Vitest)がグリーン

- [ ] **Step 2: Playwrightで実ブラウザから一連の動作を確認する**

以下を手動でPlaywright MCPツールを使って確認する(Stripeテストモードのテストカード`4242 4242 4242 4242`を使用):

1. `/premium`ページが表示され、月額・年額プランが正しく表示される
2. ログイン後、月額プランで「このプランで登録」をクリックし、Stripe Checkoutページにリダイレクトされる
3. テストカードで決済を完了し、`/premium/thanks`にリダイレクトされる
4. `/mypage`にアクセスでき、ツール利用履歴が表示される
5. 「請求ポータルを開く」でStripeのBilling Portalに遷移できる
6. 無料ユーザーで画像圧縮ツールに4枚アップロードするとエラーになり、Premiumユーザーでは成功することを確認する

- [ ] **Step 3: `TASKS.md`にPhase 21として実装内容を追記する**

`TASKS.md`の「今後の実装順序」セクションの直前に、Phase 21のセクションを追加し、Task 1〜9で実装した内容(Cashier導入・料金ページ・Checkout・Billing Portal・バッチ上限・レート制限・マイページ・特商法ページ)を`- [x]`のチェックリストとして記録する。「今後の実装順序」は「現時点で計画済みタスクは全て完了」に更新する。

- [ ] **Step 4: `SPEC.md`の該当箇所を更新する**

- §4.1の`users`テーブルの説明(`Premium導入時に is_premium 等のカラムを追加予定`)を、実際の実装(独自カラムなし、Cashierの`subscriptions`テーブルで判定)に合わせて書き換える
- §20(スコープ外)から「Premium機能・Stripe決済連携」の項目を削除する
- 新しいセクション番号でPremium/Stripe仕様の要約を追記する(詳細は本Planと`docs/superpowers/specs/2026-08-23-premium-stripe-design.md`を参照する形でよい)

- [ ] **Step 5: コミットする**

```bash
git add TASKS.md SPEC.md
git commit -m "docs: record Premium/Stripe implementation in TASKS.md and SPEC.md"
```

- [ ] **Step 6: リモートにpushする**

```bash
git push
```
