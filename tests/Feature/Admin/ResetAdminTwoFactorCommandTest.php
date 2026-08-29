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
