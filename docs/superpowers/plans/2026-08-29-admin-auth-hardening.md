# Admin認証機能の強化 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adminガード(`admins`テーブル)に、2段階認証(TOTP、必須)・パスワードリセット・パスワード変更・メールアドレス変更を追加し、パスワード強度ルールをAdmin/User双方で強化する。

**Architecture:** `Laravel\Fortify\TwoFactorAuthenticatable`トレイトと`Laravel\Fortify\Actions\*`のAction クラス(いずれもガード非依存)を`Admin`モデルに直接適用し、TOTPのコアロジックを再実装せずに再利用する。パスワードリセットはFortifyを経由せず、Laravel本体の複数ガード対応`Password Broker`を使う。ログイン・2FA設定・2FAチャレンジ・設定変更それぞれに専用のAdmin用コントローラー・ルート・Vueページを新規に用意し、既存の`/admin/login`・`/admin/logout`はUser側のFortify実装とは独立したまま拡張する。

**Tech Stack:** PHP 8.3 / Laravel 13 / Laravel Fortify(パッケージのAction/トレイトのみ再利用、HTTPルーティングは使わない) / Inertia.js v2 / Vue 3 + TypeScript / Pest

**Spec:** `docs/superpowers/specs/2026-08-29-admin-auth-hardening-design.md`

## Global Constraints

- ドキュメント成果物は日本語で作成する(CLAUDE.md)。
- Adminの新規登録(register)機能は作らない。
- User側の2FAは任意のまま維持し、必須化の対象はAdminのみ。
- 2FAの「無効化」ボタンはUIに設けない。デバイス・リカバリーコード両方紛失時は`admin:reset-two-factor`コマンドでのみ復旧する。
- Admin用パスワードルール: `Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()`。`admin:create`コマンド・パスワード変更・パスワードリセットすべてに同一ルールを適用する。
- User用パスワードルール: `Password::min(10)->uncompromised()`(記号・大文字小文字混在は必須にしない)。
- `Password::uncompromised()`はHTTPクライアント経由でHave I Been Pwned APIを呼ぶため、テストでは`tests/TestCase.php`にグローバルな`Http::fake()`のデフォルトを追加し、実際の外部通信を発生させない。
- 新規に作るAdmin向けVueページ(素のTailwind CSS、shadcn-vue不使用)は、既存の`resources/js/pages/admin/Login.vue`と同じ見た目のパターン(中央寄せカード)を踏襲する。

---

## Task 1: DBスキーマ・config・Adminモデルの拡張

**Files:**
- Create: `database/migrations/2026_08_29_100001_add_two_factor_columns_to_admins_table.php`
- Create: `database/migrations/2026_08_29_100002_create_admin_password_reset_tokens_table.php`
- Modify: `config/auth.php`
- Modify: `app/Models/Admin.php`
- Test: `tests/Feature/Admin/AdminAuthPlumbingTest.php`

**Interfaces:**
- Produces: `admins`テーブルへの`two_factor_secret`(text, nullable)・`two_factor_recovery_codes`(text, nullable)・`two_factor_confirmed_at`(timestamp, nullable)カラム追加。`admin_password_reset_tokens`テーブル(`email` primary, `token`, `created_at`)。`App\Models\Admin`が`Laravel\Fortify\TwoFactorAuthenticatable`・`Illuminate\Notifications\Notifiable`トレイトを持ち、`hasEnabledTwoFactorAuthentication()`・`recoveryCodes()`・`twoFactorQrCodeSvg()`・`sendPasswordResetNotification()`が利用可能になる。`Password::broker('admins')`が解決できる(`config/auth.php`の`passwords.admins`エントリ)。

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Models\Admin;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('an admin has no two-factor authentication enabled by default', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin-2fa-plumbing@example.com',
        'password' => bcrypt('irrelevant-for-this-test'),
    ]);

    expect($admin->hasEnabledTwoFactorAuthentication())->toBeFalse();
    expect($admin->two_factor_confirmed_at)->toBeNull();
});

test('the admins password broker sends a reset link notification to an admin', function () {
    Notification::fake();

    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin-reset-plumbing@example.com',
        'password' => bcrypt('irrelevant-for-this-test'),
    ]);

    $status = Password::broker('admins')->sendResetLink(['email' => $admin->email]);

    expect($status)->toBe(Password::RESET_LINK_SENT);
    Notification::assertSentTo($admin, ResetPassword::class);
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/AdminAuthPlumbingTest.php`
Expected: FAIL(`two_factor_secret`等のカラムが存在しない、または`Password::broker('admins')`が未定義のブローカーでエラーになる)

- [ ] **Step 3: migrationを2本作成**

`database/migrations/2026_08_29_100001_add_two_factor_columns_to_admins_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
```

`database/migrations/2026_08_29_100002_create_admin_password_reset_tokens_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
    }
};
```

Run: `php artisan migrate`
Expected: 2本のmigrationが成功する

- [ ] **Step 4: `config/auth.php`の`passwords`配列に`admins`ブローカーを追加**

`config/auth.php`の以下の箇所:

```php
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
```

を次のように変更する:

```php
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'admins' => [
            'provider' => 'admins',
            'table' => 'admin_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
```

(`providers.admins`は`admin`ガードのために既に存在するため変更不要)

- [ ] **Step 5: `App\Models\Admin`を更新**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class Admin extends Authenticatable
{
    use Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 6: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/AdminAuthPlumbingTest.php`
Expected: PASS

- [ ] **Step 7: `composer test`(Pint/PHPStan/Pest)がグリーンであることを確認**

Run: `composer test`
Expected: すべてグリーン

- [ ] **Step 8: コミット**

```bash
git add database/migrations/2026_08_29_100001_add_two_factor_columns_to_admins_table.php database/migrations/2026_08_29_100002_create_admin_password_reset_tokens_table.php config/auth.php app/Models/Admin.php tests/Feature/Admin/AdminAuthPlumbingTest.php
git commit -m "feat: add 2FA columns and password reset broker for admin guard"
```

---

## Task 2: パスワード強度ルールの強化(Admin/User)

**Files:**
- Create: `app/Concerns/AdminPasswordValidationRules.php`
- Modify: `app/Concerns/PasswordValidationRules.php`
- Modify: `app/Console/Commands/CreateAdminCommand.php`
- Modify: `tests/TestCase.php`
- Modify: `tests/Feature/Auth/RegistrationTest.php`
- Modify: `tests/Feature/Auth/PasswordResetTest.php`
- Modify: `tests/Feature/Admin/CreateAdminCommandTest.php`
- Test: `tests/Feature/Admin/CreateAdminCommandTest.php`(既存ファイルに追記)

**Interfaces:**
- Produces: `App\Concerns\AdminPasswordValidationRules::passwordRules(): array`(Task 5〜7のAdmin用パスワード変更・リセットコントローラーがこのトレイトを`use`して呼び出す)。

- [ ] **Step 1: `tests/TestCase.php`にグローバルな`Http::fake()`のデフォルトを追加**

`Password::uncompromised()`はHave I Been Pwned APIへ実際に外部HTTPリクエストを送るため、テスト全体でこれを無害化する。`tests/TestCase.php`の`setUp()`に追加:

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Password::uncompromised() calls out to the Have I Been Pwned API via
        // the Http facade. Fake it globally so tests never make a real network
        // call and never fail due to that service being unreachable; a test
        // that specifically wants to verify "known-compromised password
        // rejected" behavior can override this with its own Http::fake().
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
```

- [ ] **Step 2: 失敗するテストを`CreateAdminCommandTest.php`に追記**

既存の`tests/Feature/Admin/CreateAdminCommandTest.php`に以下を追記(既存の2テストの`'password1234'`はStep 5で修正するため、この時点ではまだ変更しない):

```php
test('admin:create rejects a password without a symbol', function () {
    $this->artisan('admin:create', [
        'name' => 'Katsuhiro',
        'email' => 'weak-password@example.com',
        'password' => 'NoSymbolHere123',
    ])->assertFailed();

    expect(\App\Models\Admin::where('email', 'weak-password@example.com')->exists())->toBeFalse();
});
```

- [ ] **Step 3: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/CreateAdminCommandTest.php`
Expected: FAIL(現在の`CreateAdminCommand`は`min:8`のみのバリデーションで、記号なしの`NoSymbolHere123`を通してしまうため、`assertFailed()`が失敗する)

- [ ] **Step 4: `AdminPasswordValidationRules`トレイトを作成**

`app/Concerns/AdminPasswordValidationRules.php`:

```php
<?php

namespace App\Concerns;

use Illuminate\Validation\Rules\Password;

trait AdminPasswordValidationRules
{
    /**
     * @return array<int, Password|string>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised(),
            'confirmed',
        ];
    }
}
```

- [ ] **Step 5: `CreateAdminCommand`をAdmin用ルールに変更**

`app/Console/Commands/CreateAdminCommand.php`の`handle()`内、次のバリデーション定義:

```php
            'password' => ['required', 'string', 'min:8'],
```

を次のように変更する:

```php
            'password' => $this->passwordRules(),
```

ファイル冒頭のuse文に`use App\Concerns\AdminPasswordValidationRules;`を追加し、`class CreateAdminCommand extends Command`の直後(クラス本体の先頭)に`use AdminPasswordValidationRules;`というトレイト利用宣言を追加する。

既存の`CreateAdminCommandTest.php`の2テストが使う`'password1234'`(記号なし)は新ルールで拒否されるため、両方とも`'Str0ng!Passw0rd'`に変更する:

```php
test('admin:create creates a new admin account', function () {
    $this->artisan('admin:create', [
        'name' => 'Katsuhiro',
        'email' => 'katsuhiro@example.com',
        'password' => 'Str0ng!Passw0rd',
    ])->assertSuccessful();

    $admin = Admin::where('email', 'katsuhiro@example.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('Katsuhiro');
});

test('admin:create rejects a duplicate email', function () {
    Admin::create([
        'name' => 'Existing',
        'email' => 'katsuhiro@example.com',
        'password' => bcrypt('Str0ng!Passw0rd'),
    ]);

    $this->artisan('admin:create', [
        'name' => 'Katsuhiro',
        'email' => 'katsuhiro@example.com',
        'password' => 'Str0ng!Passw0rd',
    ])->assertFailed();

    expect(Admin::count())->toBe(1);
});
```

(`password_confirmation`引数がなくても、Artisanコマンドの`{password}`引数バリデーションには`confirmed`ルールが実質無効であることに注意 — `Validator::make`は`password_confirmation`キー自体が存在しないため`confirmed`は素通りする。これは既存挙動のままで問題ない)

- [ ] **Step 6: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/CreateAdminCommandTest.php`
Expected: PASS(3テストすべて)

- [ ] **Step 7: User側の`PasswordValidationRules`を強化**

`app/Concerns/PasswordValidationRules.php`の以下の箇所:

```php
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }
```

を次のように変更する:

```php
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::min(10)->uncompromised(), 'confirmed'];
    }
```

- [ ] **Step 8: 影響を受ける既存User認証テストのパスワード文字列を更新**

`tests/Feature/Auth/RegistrationTest.php`内、3箇所の`'password' => 'password'`と対応する`'password_confirmation' => 'password'`をすべて`'a-secure-test-password'`に変更する(8文字の`'password'`は新ルールの`min(10)`を満たさないため)。

`tests/Feature/Auth/PasswordResetTest.php`内、`'password can be reset with valid token'`テストの`'password' => 'password'`と`'password_confirmation' => 'password'`も同様に`'a-secure-test-password'`に変更する。

- [ ] **Step 9: バックエンドテスト全体を実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Auth/ tests/Feature/Admin/CreateAdminCommandTest.php`
Expected: PASS(全件)

- [ ] **Step 10: `composer test`がグリーンであることを確認**

Run: `composer test`
Expected: すべてグリーン(433件強、既存テストの回帰がないこと)

- [ ] **Step 11: コミット**

```bash
git add app/Concerns/AdminPasswordValidationRules.php app/Concerns/PasswordValidationRules.php app/Console/Commands/CreateAdminCommand.php tests/TestCase.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/PasswordResetTest.php tests/Feature/Admin/CreateAdminCommandTest.php
git commit -m "feat: strengthen password rules for admin (12ch+symbols) and user (10ch), fake pwnedpasswords API in tests"
```

---

## Task 3: 2FA関連ミドルウェアの追加

**Files:**
- Create: `app/Http/Middleware/EnsureAdminTwoFactorConfirmed.php`
- Create: `app/Http/Middleware/EnsurePendingTwoFactorChallenge.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/Admin/AdminTwoFactorMiddlewareTest.php`

**Interfaces:**
- Produces: ミドルウェアエイリアス`'admin.2fa-confirmed'`(Task 7で既存Adminルートに適用)、`'admin.pending-2fa'`(Task 5で2FAチャレンジルートに適用)。

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth:admin', 'admin.2fa-confirmed'])
        ->get('/__test/admin-2fa-confirmed-only', fn () => 'ok');

    Route::middleware(['web', 'admin.pending-2fa'])
        ->get('/__test/admin-pending-2fa-only', fn () => 'ok');
});

test('EnsureAdminTwoFactorConfirmed redirects an admin without confirmed 2FA to the setup route', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'unconfirmed-2fa@example.com',
        'password' => bcrypt('irrelevant-for-this-test'),
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/__test/admin-2fa-confirmed-only');

    $response->assertRedirect(route('admin.two-factor.setup'));
});

test('EnsureAdminTwoFactorConfirmed allows an admin with confirmed 2FA through', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'confirmed-2fa@example.com',
        'password' => bcrypt('irrelevant-for-this-test'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/__test/admin-2fa-confirmed-only');

    $response->assertOk();
    $response->assertSee('ok');
});

test('EnsurePendingTwoFactorChallenge redirects to login when there is no pending challenge', function () {
    $response = $this->get('/__test/admin-pending-2fa-only');

    $response->assertRedirect(route('admin.login'));
});

test('EnsurePendingTwoFactorChallenge allows the request through when a challenge is pending', function () {
    $response = $this->withSession(['admin_login.id' => 1])
        ->get('/__test/admin-pending-2fa-only');

    $response->assertOk();
    $response->assertSee('ok');
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/AdminTwoFactorMiddlewareTest.php`
Expected: FAIL(ミドルウェアエイリアス`admin.2fa-confirmed`・`admin.pending-2fa`が未登録のためエラー)

- [ ] **Step 3: `EnsureAdminTwoFactorConfirmed`ミドルウェアを実装**

```php
<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorConfirmed
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin instanceof Admin && is_null($admin->two_factor_confirmed_at)) {
            return redirect()->route('admin.two-factor.setup');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: `EnsurePendingTwoFactorChallenge`ミドルウェアを実装**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePendingTwoFactorChallenge
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('admin_login.id')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: `bootstrap/app.php`にミドルウェアエイリアスを登録**

`bootstrap/app.php`の以下の箇所:

```php
use App\Http\Middleware\EnsurePremium;
```

の直後に追加:

```php
use App\Http\Middleware\EnsureAdminTwoFactorConfirmed;
use App\Http\Middleware\EnsurePendingTwoFactorChallenge;
```

(import文はアルファベット順に並べる)

`$middleware->alias(['premium' => EnsurePremium::class]);`の行を次のように変更する:

```php
        $middleware->alias([
            'premium' => EnsurePremium::class,
            'admin.2fa-confirmed' => EnsureAdminTwoFactorConfirmed::class,
            'admin.pending-2fa' => EnsurePendingTwoFactorChallenge::class,
        ]);
```

- [ ] **Step 6: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/AdminTwoFactorMiddlewareTest.php`
Expected: PASS(5件)

- [ ] **Step 7: `composer test`がグリーンであることを確認**

Run: `composer test`
Expected: すべてグリーン

- [ ] **Step 8: コミット**

```bash
git add app/Http/Middleware/EnsureAdminTwoFactorConfirmed.php app/Http/Middleware/EnsurePendingTwoFactorChallenge.php bootstrap/app.php tests/Feature/Admin/AdminTwoFactorMiddlewareTest.php
git commit -m "feat: add middleware enforcing mandatory admin 2FA and pending-challenge state"
```

---

## Task 4: 2FA設定画面(`TwoFactorAuthenticationController`)

**Files:**
- Create: `app/Http/Controllers/Admin/TwoFactorAuthenticationController.php`
- Create: `resources/js/pages/admin/TwoFactorSetup.vue`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/TwoFactorAuthenticationControllerTest.php`

**Interfaces:**
- Consumes: `Laravel\Fortify\Actions\{EnableTwoFactorAuthentication, ConfirmTwoFactorAuthentication, GenerateNewRecoveryCodes}`(既存パッケージ、コンテナ経由で解決)。
- Produces: `GET /admin/two-factor-setup`(ルート名`admin.two-factor.setup`)、`POST /admin/two-factor-setup/confirm`(`admin.two-factor.confirm`)、`POST /admin/two-factor-setup/recovery-codes`(`admin.two-factor.recovery-codes`)。Task 7が`recovery-codes`をSettings画面から呼び出す。

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function createUnconfirmedAdmin(): Admin
{
    return Admin::create([
        'name' => 'Test Admin',
        'email' => 'two-factor-setup@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);
}

test('the two-factor setup page generates a secret and shows a QR code for an unconfirmed admin', function () {
    $admin = createUnconfirmedAdmin();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.two-factor.setup'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/TwoFactorSetup')
        ->where('confirmed', false)
        ->has('qrCodeSvg'));

    expect($admin->fresh()->two_factor_secret)->not->toBeNull();
});

test('confirming with a valid code sets two_factor_confirmed_at and flashes recovery codes', function () {
    $admin = createUnconfirmedAdmin();
    $this->actingAs($admin, 'admin')->get(route('admin.two-factor.setup'));
    $admin->refresh();

    $secret = \Laravel\Fortify\Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
    $validCode = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.two-factor.confirm'), [
        'code' => $validCode,
    ]);

    $response->assertRedirect(route('admin.two-factor.setup'));
    expect($admin->fresh()->two_factor_confirmed_at)->not->toBeNull();

    $followUp = $this->actingAs($admin, 'admin')->get(route('admin.two-factor.setup'));
    $followUp->assertInertia(fn (Assert $page) => $page
        ->component('admin/TwoFactorSetup')
        ->where('confirmed', true)
        ->has('recoveryCodes', 8));
});

test('confirming with an invalid code fails validation', function () {
    $admin = createUnconfirmedAdmin();
    $this->actingAs($admin, 'admin')->get(route('admin.two-factor.setup'));

    $response = $this->actingAs($admin, 'admin')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.two-factor.confirm'), ['code' => '000000']);

    $response->assertStatus(422);
    expect($admin->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('regenerating recovery codes requires the current password and replaces the old codes', function () {
    $admin = createUnconfirmedAdmin();
    $this->actingAs($admin, 'admin')->get(route('admin.two-factor.setup'));
    $admin->refresh();

    $secret = \Laravel\Fortify\Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
    $this->actingAs($admin, 'admin')->post(route('admin.two-factor.confirm'), [
        'code' => app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret),
    ]);
    $admin->refresh();
    $oldCodes = $admin->recoveryCodes();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.two-factor.recovery-codes'), [
        'current_password' => 'Str0ng!Passw0rd',
    ]);

    $response->assertRedirect(route('admin.two-factor.setup'));
    expect($admin->fresh()->recoveryCodes())->not->toBe($oldCodes);
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/TwoFactorAuthenticationControllerTest.php`
Expected: FAIL(ルート`admin.two-factor.setup`が存在しない)

- [ ] **Step 3: `TwoFactorAuthenticationController`を実装**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class TwoFactorAuthenticationController extends Controller
{
    public function create(Request $request, EnableTwoFactorAuthentication $enable): Response
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (is_null($admin->two_factor_confirmed_at)) {
            $enable($admin);
            $admin->refresh();
        }

        $confirmed = ! is_null($admin->two_factor_confirmed_at);

        return Inertia::render('admin/TwoFactorSetup', [
            'confirmed' => $confirmed,
            'qrCodeSvg' => $confirmed ? null : $admin->twoFactorQrCodeSvg(),
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes'),
        ]);
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $confirm($admin, $request->string('code')->toString());

        $admin->refresh();

        return redirect()->route('admin.two-factor.setup')
            ->with('two_factor_recovery_codes', $admin->recoveryCodes());
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:admin']]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $generate($admin);
        $admin->refresh();

        return redirect()->route('admin.two-factor.setup')
            ->with('two_factor_recovery_codes', $admin->recoveryCodes());
    }
}
```

- [ ] **Step 4: `TwoFactorSetup.vue`を実装**

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    confirmed: boolean;
    qrCodeSvg: string | null;
    recoveryCodes: string[] | null;
}>();

const form = useForm({
    code: '',
});

function submit() {
    form.post('/admin/two-factor-setup/confirm', {
        onFinish: () => form.reset('code'),
    });
}
</script>

<template>
    <Head title="二段階認証の設定 - Admin" />

    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12"
    >
        <div
            class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
        >
            <h1 class="text-lg font-bold text-gray-900">二段階認証の設定</h1>
            <p class="mt-2 text-sm text-gray-600">
                管理者アカウントには二段階認証の設定が必須です。設定が完了するまで他の画面は利用できません。
            </p>

            <div
                v-if="recoveryCodes"
                class="mt-6 rounded-md border border-amber-300 bg-amber-50 p-4"
            >
                <p class="text-sm font-medium text-amber-800">
                    リカバリーコード(この画面でのみ表示されます。安全な場所に保存してください)
                </p>
                <ul
                    class="mt-2 grid grid-cols-2 gap-1 font-mono text-xs text-amber-900"
                >
                    <li v-for="recoveryCode in recoveryCodes" :key="recoveryCode">
                        {{ recoveryCode }}
                    </li>
                </ul>
            </div>

            <template v-if="!confirmed">
                <div
                    v-if="qrCodeSvg"
                    class="mt-6 rounded-md border border-gray-200 p-4"
                    v-html="qrCodeSvg"
                />

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label
                            for="code"
                            class="block text-sm font-medium text-gray-700"
                        >
                            認証アプリに表示されたコード
                        </label>
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                        />
                        <p
                            v-if="form.errors.code"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        :disabled="form.processing"
                    >
                        確認する
                    </button>
                </form>
            </template>

            <p v-else class="mt-6 text-sm text-green-700">
                二段階認証は設定済みです。
            </p>
        </div>
    </div>
</template>
```

- [ ] **Step 5: `routes/admin.php`にルートを追加**

`Route::middleware('auth:admin')->group(function () {`の直後、既存の`dashboard`等より前に追加:

```php
        Route::get('two-factor-setup', [TwoFactorAuthenticationController::class, 'create'])->name('two-factor.setup');
        Route::post('two-factor-setup/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('two-factor-setup/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
```

ファイル冒頭の`use`文に追加:

```php
use App\Http\Controllers\Admin\TwoFactorAuthenticationController;
```

- [ ] **Step 6: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/TwoFactorAuthenticationControllerTest.php`
Expected: PASS(4件)

- [ ] **Step 7: `npm run types:check` / `lint:check` / `format:check`を実行**

Run: `npm run types:check && npm run lint:check && npm run format:check`
Expected: すべてグリーン(formatエラーが出た場合は`npm run format`を実行してから再確認)

- [ ] **Step 8: `composer test`がグリーンであることを確認**

Run: `composer test`
Expected: すべてグリーン

- [ ] **Step 9: コミット**

```bash
git add app/Http/Controllers/Admin/TwoFactorAuthenticationController.php resources/js/pages/admin/TwoFactorSetup.vue routes/admin.php tests/Feature/Admin/TwoFactorAuthenticationControllerTest.php
git commit -m "feat: add admin two-factor authentication setup screen"
```

---

## Task 5: ログインの2段階化と2FAチャレンジ画面

**Files:**
- Create: `app/Http/Controllers/Admin/TwoFactorChallengeController.php`
- Create: `resources/js/pages/admin/TwoFactorChallenge.vue`
- Modify: `app/Http/Controllers/Admin/AuthenticatedSessionController.php`
- Modify: `resources/js/pages/admin/Login.vue`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/AuthenticatedSessionControllerTest.php`(新規、または既存のログインテストファイルに追記。既存ファイル名を確認して合わせる)
- Test: `tests/Feature/Admin/TwoFactorChallengeControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Admin::hasEnabledTwoFactorAuthentication()`・`recoveryCodes()`・`replaceRecoveryCode()`(Task 1で追加)。
- Produces: `GET /admin/two-factor-challenge`(`admin.two-factor.challenge`)、`POST /admin/two-factor-challenge`(`admin.two-factor.challenge.store`)。

- [ ] **Step 1: 既存のAdminログインテストファイルを確認**

Run: `find tests -iname "*AuthenticatedSessionController*" -path "*Admin*"`

見つかったファイル(見つからない場合は`tests/Feature/Admin/AuthenticatedSessionControllerTest.php`を新規作成)に、以降のテストを追記・修正する。

- [ ] **Step 2: 失敗するテストを書く**

既存のログインテストに以下を追記(既存の「ログイン成功」テストが2FA未設定Adminを前提にしている場合はそのまま残し、以下を追加):

```php
test('logging in with 2FA already confirmed does not fully authenticate yet and redirects to the challenge', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'confirmed-login@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'confirmed-login@example.com',
        'password' => 'Str0ng!Passw0rd',
    ]);

    $response->assertRedirect(route('admin.two-factor.challenge'));
    $this->assertGuest('admin');
    expect(session('admin_login.id'))->toBe($admin->id);
});

test('logging in without 2FA confirmed fully authenticates immediately', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'unconfirmed-login@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'unconfirmed-login@example.com',
        'password' => 'Str0ng!Passw0rd',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');
});
```

`tests/Feature/Admin/TwoFactorChallengeControllerTest.php`(新規):

```php
<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

function createConfirmedAdminForChallenge(): Admin
{
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'challenge-test@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    app(\Laravel\Fortify\Actions\EnableTwoFactorAuthentication::class)($admin);
    $admin->refresh();

    $secret = \Laravel\Fortify\Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
    $code = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);
    app(\Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication::class)($admin, $code);

    return $admin->refresh();
}

test('the challenge page is inaccessible without a pending login', function () {
    $response = $this->get(route('admin.two-factor.challenge'));

    $response->assertRedirect(route('admin.login'));
});

test('a valid code completes the login', function () {
    $admin = createConfirmedAdminForChallenge();
    $secret = \Laravel\Fortify\Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
    $code = app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);

    $response = $this->withSession(['admin_login.id' => $admin->id])
        ->post(route('admin.two-factor.challenge.store'), [
            'code' => $code,
        ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');
});

test('a valid recovery code completes the login and is consumed', function () {
    $admin = createConfirmedAdminForChallenge();
    $recoveryCode = $admin->recoveryCodes()[0];

    $response = $this->withSession(['admin_login.id' => $admin->id])
        ->post(route('admin.two-factor.challenge.store'), [
            'recovery_code' => $recoveryCode,
        ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');
    expect($admin->fresh()->recoveryCodes())->not->toContain($recoveryCode);
});

test('an invalid code fails and does not authenticate', function () {
    $admin = createConfirmedAdminForChallenge();

    $response = $this->withSession(['admin_login.id' => $admin->id])
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.two-factor.challenge.store'), ['code' => '000000']);

    $response->assertStatus(422);
    $this->assertGuest('admin');
});
```

- [ ] **Step 3: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/TwoFactorChallengeControllerTest.php`
Expected: FAIL(ルート`admin.two-factor.challenge`が存在しない)

- [ ] **Step 4: `AuthenticatedSessionController::store()`を2段階ログインに変更**

`app/Http/Controllers/Admin/AuthenticatedSessionController.php`の`store()`メソッド全体を次のように置き換える:

```php
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('admin')->validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $admin = Admin::where('email', $credentials['email'])->firstOrFail();

        if ($admin->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('admin_login.id', $admin->id);
            $request->session()->put('admin_login.remember', $remember);

            return redirect()->route('admin.two-factor.challenge');
        }

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
```

ファイル冒頭に`use App\Models\Admin;`を追加する。

- [ ] **Step 5: `TwoFactorChallengeController`を実装**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class TwoFactorChallengeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('admin/TwoFactorChallenge');
    }

    public function store(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $admin = Admin::findOrFail($request->session()->get('admin_login.id'));

        $code = (string) $request->input('code', '');
        $recoveryCode = (string) $request->input('recovery_code', '');

        $validCode = $code !== '' && ! is_null($admin->two_factor_secret) && $provider->verify(
            Fortify::currentEncrypter()->decrypt($admin->two_factor_secret),
            $code,
        );

        $validRecoveryCode = $recoveryCode !== '' && in_array($recoveryCode, $admin->recoveryCodes(), true);

        if (! $validCode && ! $validRecoveryCode) {
            throw ValidationException::withMessages([
                'code' => '認証コードが正しくありません。',
            ]);
        }

        if ($validRecoveryCode) {
            $admin->replaceRecoveryCode($recoveryCode);
        }

        $remember = (bool) $request->session()->pull('admin_login.remember', false);
        $request->session()->forget('admin_login.id');

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
```

- [ ] **Step 6: `TwoFactorChallenge.vue`を実装**

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const useRecoveryCode = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

function submit() {
    form.post('/admin/two-factor-challenge', {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head title="二段階認証 - Admin" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
        >
            <h1 class="text-lg font-bold text-gray-900">二段階認証</h1>
            <p class="mt-2 text-sm text-gray-600">
                認証アプリに表示されているコードを入力してください。
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div v-if="!useRecoveryCode">
                    <label
                        for="code"
                        class="block text-sm font-medium text-gray-700"
                        >認証コード</label
                    >
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p
                        v-if="form.errors.code"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <div v-else>
                    <label
                        for="recovery_code"
                        class="block text-sm font-medium text-gray-700"
                        >リカバリーコード</label
                    >
                    <input
                        id="recovery_code"
                        v-model="form.recovery_code"
                        type="text"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p
                        v-if="form.errors.code"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="form.processing"
                >
                    確認する
                </button>

                <button
                    type="button"
                    class="w-full text-center text-sm text-gray-500 underline"
                    @click="useRecoveryCode = !useRecoveryCode"
                >
                    {{
                        useRecoveryCode
                            ? '認証コードを使う'
                            : 'リカバリーコードを使う'
                    }}
                </button>
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 7: `Login.vue`に「パスワードをお忘れですか?」リンクを追加**

`resources/js/pages/admin/Login.vue`の`<script setup>`冒頭を次のように変更:

```vue
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
```

`</form>`の直後(カードのdiv内、`</div>`の前)に追加:

```vue
            <Link
                href="/admin/forgot-password"
                class="mt-4 block text-center text-sm text-gray-500 underline"
            >
                パスワードをお忘れですか?
            </Link>
```

- [ ] **Step 8: `routes/admin.php`にルートを追加**

`Route::middleware('guest:admin')->group(function () {`のブロックはそのまま(Task 6でここに追加する)。新規に`admin.pending-2fa`グループを、`guest:admin`グループの直後・`auth:admin`グループの前に追加:

```php
    Route::middleware('admin.pending-2fa')->group(function () {
        Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('two-factor.challenge.store');
    });
```

ファイル冒頭の`use`文に追加:

```php
use App\Http\Controllers\Admin\TwoFactorChallengeController;
```

- [ ] **Step 9: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/`
Expected: PASS(全件)

- [ ] **Step 10: フロントエンドの型チェック・Lint・Formatを実行**

Run: `npm run types:check && npm run lint:check && npm run format:check`
Expected: すべてグリーン

- [ ] **Step 11: `composer test`がグリーンであることを確認**

Run: `composer test`
Expected: すべてグリーン

- [ ] **Step 12: コミット**

```bash
git add app/Http/Controllers/Admin/TwoFactorChallengeController.php app/Http/Controllers/Admin/AuthenticatedSessionController.php resources/js/pages/admin/TwoFactorChallenge.vue resources/js/pages/admin/Login.vue routes/admin.php tests/Feature/Admin/
git commit -m "feat: split admin login into password + mandatory 2FA challenge"
```

---

## Task 6: Adminパスワードリセット

**Files:**
- Create: `app/Http/Controllers/Admin/PasswordResetLinkController.php`
- Create: `app/Http/Controllers/Admin/NewPasswordController.php`
- Create: `resources/js/pages/admin/ForgotPassword.vue`
- Create: `resources/js/pages/admin/ResetPassword.vue`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/PasswordResetTest.php`

**Interfaces:**
- Consumes: `App\Concerns\AdminPasswordValidationRules::passwordRules()`(Task 2)、`Password::broker('admins')`(Task 1)。
- Produces: `GET /admin/forgot-password`(`admin.password.request`)、`POST /admin/forgot-password`(`admin.password.email`)、`GET /admin/reset-password/{token}`(`admin.password.reset`)、`POST /admin/reset-password`(`admin.password.store`)。

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Models\Admin;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('the forgot-password page renders', function () {
    $response = $this->get(route('admin.password.request'));

    $response->assertSuccessful();
});

test('a reset link is sent for an existing admin', function () {
    Notification::fake();

    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'reset-flow@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class);
});

test('requesting a reset link for a non-existent email does not reveal whether the account exists', function () {
    Notification::fake();

    $response = $this->post(route('admin.password.email'), ['email' => 'nobody@example.com']);

    $response->assertSessionHasNoErrors();
});

test('the reset-password page renders with a token', function () {
    $response = $this->get(route('admin.password.reset', ['token' => 'dummy-token']));

    $response->assertSuccessful();
});

test('a password can be reset with a valid token and meets the admin password policy', function () {
    Notification::fake();

    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'reset-success@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
        $response = $this->post(route('admin.password.store'), [
            'token' => $notification->token,
            'email' => $admin->email,
            'password' => 'N3w!SecurePassword',
            'password_confirmation' => 'N3w!SecurePassword',
        ]);

        $response->assertRedirect(route('admin.login'));

        return true;
    });

    expect(Hash::check('N3w!SecurePassword', $admin->fresh()->password))->toBeTrue();
});

test('resetting to a password that fails the admin policy is rejected', function () {
    Notification::fake();

    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'reset-weak@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('admin.password.store'), [
                'token' => $notification->token,
                'email' => $admin->email,
                'password' => 'nosymbol123',
                'password_confirmation' => 'nosymbol123',
            ]);

        $response->assertStatus(422);

        return true;
    });
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/PasswordResetTest.php`
Expected: FAIL(ルート`admin.password.request`等が存在しない)

- [ ] **Step 3: `PasswordResetLinkController`を実装**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('admin/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('admins')->sendResetLink(
            $request->only('email'),
        );

        return back()->with('status', 'パスワードリセット用のメールを送信しました(該当するアカウントが存在する場合)。');
    }
}
```

- [ ] **Step 4: `NewPasswordController`を実装**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\AdminPasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    use AdminPasswordValidationRules;

    public function create(Request $request): Response
    {
        return Inertia::render('admin/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $admin, string $password) {
                $admin->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($admin));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()->route('admin.login')->with('status', __($status));
    }
}
```

- [ ] **Step 5: `ForgotPassword.vue`を実装**

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();

const form = useForm({ email: '' });

function submit() {
    form.post('/admin/forgot-password');
}
</script>

<template>
    <Head title="パスワードをお忘れですか? - Admin" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
        >
            <h1 class="text-lg font-bold text-gray-900">
                パスワードをお忘れですか?
            </h1>
            <p class="mt-2 text-sm text-gray-600">
                登録済みのメールアドレスを入力してください。パスワード再設定用のリンクを送信します。
            </p>

            <p v-if="status" class="mt-4 text-sm text-green-700">
                {{ status }}
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label
                        for="email"
                        class="block text-sm font-medium text-gray-700"
                        >メールアドレス</label
                    >
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p
                        v-if="form.errors.email"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="form.processing"
                >
                    送信する
                </button>
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 6: `ResetPassword.vue`を実装**

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ token: string; email: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/admin/reset-password');
}
</script>

<template>
    <Head title="パスワードの再設定 - Admin" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
        >
            <h1 class="text-lg font-bold text-gray-900">
                パスワードの再設定
            </h1>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label
                        for="email"
                        class="block text-sm font-medium text-gray-700"
                        >メールアドレス</label
                    >
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p
                        v-if="form.errors.email"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label
                        for="password"
                        class="block text-sm font-medium text-gray-700"
                        >新しいパスワード</label
                    >
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        12文字以上、大文字・小文字・数字・記号をそれぞれ1文字以上含めてください。
                    </p>
                    <p
                        v-if="form.errors.password"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <div>
                    <label
                        for="password_confirmation"
                        class="block text-sm font-medium text-gray-700"
                    >
                        新しいパスワード(確認)
                    </label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="form.processing"
                >
                    パスワードを再設定する
                </button>
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 7: `routes/admin.php`の`guest:admin`グループに追加**

```php
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('password.email');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('password.store');
    });
```

ファイル冒頭の`use`文に追加:

```php
use App\Http\Controllers\Admin\NewPasswordController;
use App\Http\Controllers\Admin\PasswordResetLinkController;
```

- [ ] **Step 8: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/PasswordResetTest.php`
Expected: PASS(6件)

- [ ] **Step 9: フロントエンドの型チェック・Lint・Formatを実行**

Run: `npm run types:check && npm run lint:check && npm run format:check`
Expected: すべてグリーン

- [ ] **Step 10: `composer test`がグリーンであることを確認**

Run: `composer test`
Expected: すべてグリーン

- [ ] **Step 11: コミット**

```bash
git add app/Http/Controllers/Admin/PasswordResetLinkController.php app/Http/Controllers/Admin/NewPasswordController.php resources/js/pages/admin/ForgotPassword.vue resources/js/pages/admin/ResetPassword.vue routes/admin.php tests/Feature/Admin/PasswordResetTest.php
git commit -m "feat: add admin password reset flow"
```

---

## Task 7: 設定画面・既存Adminルートへの2FA必須化の適用

**Files:**
- Create: `app/Http/Controllers/Admin/SettingsController.php`
- Create: `resources/js/pages/admin/Settings.vue`
- Modify: `routes/admin.php`
- Modify: `resources/js/layouts/AdminLayout.vue`
- Modify: `tests/Pest.php`
- Test: `tests/Feature/Admin/SettingsControllerTest.php`

**Interfaces:**
- Consumes: `App\Concerns\AdminPasswordValidationRules::passwordRules()`(Task 2)。
- Produces: `GET /admin/settings`(`admin.settings.edit`)、`PUT /admin/settings/password`(`admin.settings.password`)、`PUT /admin/settings/email`(`admin.settings.email`)。既存の`dashboard`/`users.*`/`posts.*`/`ui-pattern-snippets.*`/`color-examples.*`ルートすべてが`admin.2fa-confirmed`ミドルウェアで保護される。

- [ ] **Step 1: `tests/Pest.php`の`actingAsAdmin()`ヘルパーを更新**

`tests/Pest.php`の以下の箇所:

```php
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
```

を次のように変更する(2FA確認済みAdminとしてログインする、既存の多数のAdmin系Featureテストが引き続き通るようにするため):

```php
function actingAsAdmin(): Admin
{
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
        'two_factor_confirmed_at' => now(),
    ]);

    test()->actingAs($admin, 'admin');

    return $admin;
}
```

- [ ] **Step 2: 失敗するテストを書く**

```php
<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

test('the settings page renders for a 2FA-confirmed admin', function () {
    $admin = actingAsAdmin();

    $response = $this->get(route('admin.settings.edit'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/Settings')
        ->where('email', $admin->email)
        ->where('twoFactorConfirmed', true));
});

test('a 2FA-unconfirmed admin is redirected away from settings to the setup page', function () {
    $admin = Admin::create([
        'name' => 'Unconfirmed',
        'email' => 'settings-unconfirmed@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.settings.edit'));

    $response->assertRedirect(route('admin.two-factor.setup'));
});

test('changing the password requires the current password and the admin policy', function () {
    actingAsAdmin();

    $response = $this->put(route('admin.settings.password'), [
        'current_password' => 'wrong-password',
        'password' => 'N3w!SecurePassword',
        'password_confirmation' => 'N3w!SecurePassword',
    ]);

    $response->assertSessionHasErrors('current_password');
});

test('changing the password succeeds with the correct current password and a compliant new password', function () {
    $admin = actingAsAdmin();

    $response = $this->put(route('admin.settings.password'), [
        'current_password' => 'password1234',
        'password' => 'N3w!SecurePassword',
        'password_confirmation' => 'N3w!SecurePassword',
    ]);

    $response->assertRedirect();
    expect(Hash::check('N3w!SecurePassword', $admin->fresh()->password))->toBeTrue();
});

test('changing the email requires the current password and rejects duplicates', function () {
    actingAsAdmin();
    Admin::create([
        'name' => 'Other',
        'email' => 'taken@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $response = $this->put(route('admin.settings.email'), [
        'current_password' => 'password1234',
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

test('changing the email succeeds with the correct current password and a unique address', function () {
    $admin = actingAsAdmin();

    $response = $this->put(route('admin.settings.email'), [
        'current_password' => 'password1234',
        'email' => 'new-email@example.com',
    ]);

    $response->assertRedirect();
    expect($admin->fresh()->email)->toBe('new-email@example.com');
});

test('an admin without confirmed 2FA cannot reach the dashboard', function () {
    $admin = Admin::create([
        'name' => 'Unconfirmed',
        'email' => 'dashboard-unconfirmed@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.two-factor.setup'));
});
```

- [ ] **Step 3: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/SettingsControllerTest.php`
Expected: FAIL(ルート`admin.settings.edit`が存在しない。最後のテストは`admin.dashboard`がまだ`admin.2fa-confirmed`で保護されていないため失敗する)

- [ ] **Step 4: `SettingsController`を実装**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\AdminPasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use AdminPasswordValidationRules;

    public function edit(): Response
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return Inertia::render('admin/Settings', [
            'email' => $admin->email,
            'twoFactorConfirmed' => ! is_null($admin->two_factor_confirmed_at),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => $this->passwordRules(),
        ]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $admin->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
        ])->save();

        return back()->with('status', 'パスワードを変更しました。');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore($admin->id)],
        ]);

        $admin->forceFill([
            'email' => $request->string('email')->toString(),
        ])->save();

        return back()->with('status', 'メールアドレスを変更しました。');
    }
}
```

- [ ] **Step 5: `Settings.vue`を実装**

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

const props = defineProps<{
    email: string;
    twoFactorConfirmed: boolean;
    status?: string;
}>();

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const emailForm = useForm({
    current_password: '',
    email: props.email,
});

const recoveryForm = useForm({
    current_password: '',
});

function submitPassword() {
    passwordForm.put('/admin/settings/password', {
        onFinish: () => passwordForm.reset(),
    });
}

function submitEmail() {
    emailForm.put('/admin/settings/email', {
        onFinish: () => emailForm.reset('current_password'),
    });
}

function submitRecoveryCodes() {
    recoveryForm.post('/admin/two-factor-setup/recovery-codes', {
        onFinish: () => recoveryForm.reset(),
    });
}
</script>

<template>
    <Head title="設定 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">設定</h1>
        </template>

        <div class="max-w-xl space-y-8">
            <p
                v-if="status"
                class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700"
            >
                {{ status }}
            </p>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-bold text-gray-900">パスワード変更</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitPassword">
                    <div>
                        <label
                            for="current_password"
                            class="block text-sm font-medium text-gray-700"
                            >現在のパスワード</label
                        >
                        <input
                            id="current_password"
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="passwordForm.errors.current_password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>
                    <div>
                        <label
                            for="password"
                            class="block text-sm font-medium text-gray-700"
                            >新しいパスワード</label
                        >
                        <input
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="passwordForm.errors.password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>
                    <div>
                        <label
                            for="password_confirmation"
                            class="block text-sm font-medium text-gray-700"
                        >
                            新しいパスワード(確認)
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="submit"
                        class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        :disabled="passwordForm.processing"
                    >
                        パスワードを変更する
                    </button>
                </form>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-bold text-gray-900">
                    メールアドレス変更
                </h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEmail">
                    <div>
                        <label
                            for="email"
                            class="block text-sm font-medium text-gray-700"
                            >新しいメールアドレス</label
                        >
                        <input
                            id="email"
                            v-model="emailForm.email"
                            type="email"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="emailForm.errors.email"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ emailForm.errors.email }}
                        </p>
                    </div>
                    <div>
                        <label
                            for="email_current_password"
                            class="block text-sm font-medium text-gray-700"
                        >
                            現在のパスワード
                        </label>
                        <input
                            id="email_current_password"
                            v-model="emailForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="emailForm.errors.current_password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ emailForm.errors.current_password }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        :disabled="emailForm.processing"
                    >
                        メールアドレスを変更する
                    </button>
                </form>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-bold text-gray-900">二段階認証</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ twoFactorConfirmed ? '設定済みです。' : '未設定です。' }}
                </p>
                <form
                    class="mt-4 space-y-4"
                    @submit.prevent="submitRecoveryCodes"
                >
                    <div>
                        <label
                            for="recovery_current_password"
                            class="block text-sm font-medium text-gray-700"
                        >
                            現在のパスワード
                        </label>
                        <input
                            id="recovery_current_password"
                            v-model="recoveryForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p
                            v-if="recoveryForm.errors.current_password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ recoveryForm.errors.current_password }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="recoveryForm.processing"
                    >
                        リカバリーコードを再生成する
                    </button>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>
```

- [ ] **Step 6: `routes/admin.php`の`auth:admin`グループ以下を全面的に書き換え**

`routes/admin.php`全体を次の内容に置き換える:

```php
<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\ColorExampleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NewPasswordController;
use App\Http\Controllers\Admin\PasswordResetLinkController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TwoFactorAuthenticationController;
use App\Http\Controllers\Admin\TwoFactorChallengeController;
use App\Http\Controllers\Admin\UiPatternSnippetController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('password.email');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('password.store');
    });

    Route::middleware('admin.pending-2fa')->group(function () {
        Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('two-factor.challenge.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::get('two-factor-setup', [TwoFactorAuthenticationController::class, 'create'])->name('two-factor.setup');
        Route::post('two-factor-setup/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('two-factor-setup/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::middleware('admin.2fa-confirmed')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
            Route::put('settings/email', [SettingsController::class, 'updateEmail'])->name('settings.email');

            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

            Route::get('posts', [PostController::class, 'index'])->name('posts.index');
            Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
            Route::post('posts', [PostController::class, 'store'])->name('posts.store');
            Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
            Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
            Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

            Route::get('ui-pattern-snippets', [UiPatternSnippetController::class, 'index'])->name('ui-pattern-snippets.index');
            Route::get('ui-pattern-snippets/create', [UiPatternSnippetController::class, 'create'])->name('ui-pattern-snippets.create');
            Route::post('ui-pattern-snippets', [UiPatternSnippetController::class, 'store'])->name('ui-pattern-snippets.store');
            Route::get('ui-pattern-snippets/{snippet}/edit', [UiPatternSnippetController::class, 'edit'])->name('ui-pattern-snippets.edit');
            Route::put('ui-pattern-snippets/{snippet}', [UiPatternSnippetController::class, 'update'])->name('ui-pattern-snippets.update');
            Route::delete('ui-pattern-snippets/{snippet}', [UiPatternSnippetController::class, 'destroy'])->name('ui-pattern-snippets.destroy');

            Route::get('color-examples', [ColorExampleController::class, 'index'])->name('color-examples.index');
            Route::get('color-examples/create', [ColorExampleController::class, 'create'])->name('color-examples.create');
            Route::post('color-examples', [ColorExampleController::class, 'store'])->name('color-examples.store');
            Route::get('color-examples/{colorExample}/edit', [ColorExampleController::class, 'edit'])->name('color-examples.edit');
            Route::put('color-examples/{colorExample}', [ColorExampleController::class, 'update'])->name('color-examples.update');
            Route::delete('color-examples/{colorExample}', [ColorExampleController::class, 'destroy'])->name('color-examples.destroy');
        });
    });
});
```

- [ ] **Step 7: `AdminLayout.vue`のナビゲーションに「設定」を追加**

`resources/js/layouts/AdminLayout.vue`の`NAV_ITEMS`配列末尾(`カラー例管理`の項目の後)に追加:

```typescript
    {
        label: '設定',
        routeName: 'admin.settings.edit',
        activePattern: 'admin.settings.*',
    },
```

- [ ] **Step 8: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/`
Expected: PASS(全件。既存のDashboardControllerTest・UserControllerTest等も`actingAsAdmin()`ヘルパー経由でグリーンのまま)

- [ ] **Step 9: フロントエンドの型チェック・Lint・Formatを実行**

Run: `npm run types:check && npm run lint:check && npm run format:check`
Expected: すべてグリーン

- [ ] **Step 10: `composer test`がグリーンであることを確認**

Run: `composer test`
Expected: すべてグリーン

- [ ] **Step 11: コミット**

```bash
git add app/Http/Controllers/Admin/SettingsController.php resources/js/pages/admin/Settings.vue routes/admin.php resources/js/layouts/AdminLayout.vue tests/Pest.php tests/Feature/Admin/SettingsControllerTest.php
git commit -m "feat: add admin settings screen and enforce mandatory 2FA on all admin routes"
```

---

## Task 8: 2FAリカバリー用Artisanコマンド

**Files:**
- Create: `app/Console/Commands/ResetAdminTwoFactorCommand.php`
- Test: `tests/Feature/Admin/ResetAdminTwoFactorCommandTest.php`

**Interfaces:**
- Produces: `admin:reset-two-factor {email}` Artisanコマンド。SSH経由の復旧手段(§スコープ・エラーハンドリング参照)。

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

test('admin:reset-two-factor clears the two-factor columns for the given admin', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'reset-2fa@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
        'two_factor_secret' => 'encrypted-secret-placeholder',
        'two_factor_recovery_codes' => 'encrypted-codes-placeholder',
        'two_factor_confirmed_at' => now(),
    ]);

    $this->artisan('admin:reset-two-factor', ['email' => $admin->email])
        ->assertSuccessful();

    $admin->refresh();

    expect($admin->two_factor_secret)->toBeNull();
    expect($admin->two_factor_recovery_codes)->toBeNull();
    expect($admin->two_factor_confirmed_at)->toBeNull();
});

test('admin:reset-two-factor fails for an unknown email', function () {
    $this->artisan('admin:reset-two-factor', ['email' => 'nobody@example.com'])
        ->assertFailed();
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/ResetAdminTwoFactorCommandTest.php`
Expected: FAIL(コマンド`admin:reset-two-factor`が存在しない)

- [ ] **Step 3: `ResetAdminTwoFactorCommand`を実装**

```php
<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class ResetAdminTwoFactorCommand extends Command
{
    protected $signature = 'admin:reset-two-factor {email}';

    protected $description = 'Clear two-factor authentication for an admin account (recovery when device and codes are both lost)';

    public function handle(): int
    {
        $admin = Admin::where('email', $this->argument('email'))->first();

        if (! $admin) {
            $this->error('指定されたメールアドレスのAdminアカウントが見つかりません。');

            return self::FAILURE;
        }

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("Admin {$admin->email} の二段階認証をリセットしました。次回ログイン後、再設定が必要です。");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Admin/ResetAdminTwoFactorCommandTest.php`
Expected: PASS(2件)

- [ ] **Step 5: `composer test`がグリーンであることを確認**

Run: `composer test`
Expected: すべてグリーン

- [ ] **Step 6: コミット**

```bash
git add app/Console/Commands/ResetAdminTwoFactorCommand.php tests/Feature/Admin/ResetAdminTwoFactorCommandTest.php
git commit -m "feat: add admin:reset-two-factor recovery command"
```

---

## Task 9: 最終検証

**Files:**
- なし(検証のみ)

**Interfaces:**
- Consumes: Task 1〜8ですべて実装済みの機能一式
- Produces: なし

- [ ] **Step 1: バックエンド全体チェック**

Run: `composer test`
Expected: Pint/PHPStan/Pestすべてグリーン

- [ ] **Step 2: フロントエンド全体チェック**

Run: `npm run lint:check && npm run format:check && npm run types:check && npx vitest run`
Expected: すべてグリーン

- [ ] **Step 3: 開発サーバーを起動しPlaywrightで実ブラウザ確認**

`php artisan serve`と`npm run dev`を起動し、Playwrightで以下を確認する:
- `admin:create`で新規Adminを作成し、`/admin/login`でログイン→2FA未設定のため強制的に`/admin/two-factor-setup`に遷移すること
- QRコードが表示されること、認証アプリ(またはテスト用にPHPの`pragmarx/google2fa`でワンタイムコードを計算するスクリプト)で生成したコードで確認できること、リカバリーコードが1度だけ表示されること
- 確認後、`/admin/dashboard`等の他画面にアクセスできるようになること
- 一度ログアウトし、再度`/admin/login`でログインすると、今度は`/admin/two-factor-challenge`に遷移すること。正しいコードで完全ログインできること
- `/admin/login`の「パスワードをお忘れですか?」リンクから`/admin/forgot-password`→リセットメール送信→`/admin/reset-password/{token}`→新パスワード設定が一連で動作すること(ローカルではMailtrapやログドライバでメール内容を確認)
- `/admin/settings`でパスワード変更・メールアドレス変更・リカバリーコード再生成がそれぞれ動作すること
- `admin:reset-two-factor {email}`実行後、該当Adminが次回ログイン時に再度`/admin/two-factor-setup`に誘導されること

- [ ] **Step 4: 開発サーバーを停止**

```bash
pkill -f "artisan serve"; pkill -f "vite"
```
