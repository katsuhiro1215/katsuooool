<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

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

    $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
    $validCode = app(Google2FA::class)->getCurrentOtp($secret);

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

    $secret = Fortify::currentEncrypter()->decrypt($admin->two_factor_secret);
    $this->actingAs($admin, 'admin')->post(route('admin.two-factor.confirm'), [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ]);
    $admin->refresh();
    $oldCodes = $admin->recoveryCodes();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.two-factor.recovery-codes'), [
        'current_password' => 'Str0ng!Passw0rd',
    ]);

    $response->assertRedirect(route('admin.two-factor.setup'));
    expect($admin->fresh()->recoveryCodes())->not->toBe($oldCodes);
});
