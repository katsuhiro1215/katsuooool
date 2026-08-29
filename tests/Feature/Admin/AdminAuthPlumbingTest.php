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
