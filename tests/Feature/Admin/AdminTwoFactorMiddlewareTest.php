<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth:admin', 'admin.2fa-confirmed'])
        ->get('/__test/admin-2fa-confirmed-only', fn () => 'ok');

    Route::middleware(['web', 'admin.pending-2fa'])
        ->get('/__test/admin-pending-2fa-only', fn () => 'ok');

    // `admin.two-factor.setup`はTask 4で実ルートとして定義される想定のため、
    // このテストではリダイレクト先を検証するための仮ルートを用意する。
    // 注意: `name()`は`get()`より前に呼び出す必要がある。ブート後に動的登録した
    // ルートは、`get()`実行時点(ルート登録時点)の名前でルックアップテーブルに
    // 記録されるため、`get()`の後に`name()`を連結すると名前が反映されない。
    Route::name('admin.two-factor.setup')
        ->middleware(['web'])
        ->get('/__test/admin-two-factor-setup', fn () => 'setup');
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
    ]);

    // `two_factor_confirmed_at`はマスアサインメント対象外(Adminモデルの$fillableに含まれない)のため、
    // 個別に設定する。
    $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

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
