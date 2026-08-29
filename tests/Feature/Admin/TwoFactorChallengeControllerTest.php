<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

function createConfirmedAdminForChallenge(): Admin
{
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'challenge-test@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    app(EnableTwoFactorAuthentication::class)($admin);
    $admin->refresh();

    $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    app(ConfirmTwoFactorAuthentication::class)($admin, $code);

    return $admin->refresh();
}

/**
 * TwoFactorAuthenticationProvider::verify() はコードのリプレイ防止のため、
 * 同一タイムステップで一度使用したコードを再度有効と判定しない。
 * 2FA確認時に使用したコードとログインチャレンジで使うコードが衝突しない
 * よう、次のタイムステップのコードを生成する。
 */
function nextOtpForChallenge(Admin $admin): string
{
    $engine = app(Google2FA::class);
    $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);

    return $engine->oathTotp($secret, $engine->getTimestamp() + 1);
}

test('the challenge page is inaccessible without a pending login', function () {
    $response = $this->get(route('admin.two-factor.challenge'));

    $response->assertRedirect(route('admin.login'));
});

test('a valid code completes the login', function () {
    $admin = createConfirmedAdminForChallenge();
    $code = nextOtpForChallenge($admin);

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
