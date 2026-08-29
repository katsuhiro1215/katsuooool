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
