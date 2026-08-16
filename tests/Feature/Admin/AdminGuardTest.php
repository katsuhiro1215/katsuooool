<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

test('admin can authenticate via the admin guard', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    expect(Auth::guard('admin')->attempt([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]))->toBeTrue();

    expect(Auth::guard('admin')->id())->toBe($admin->id);
});

test('the default web guard does not authenticate admins', function () {
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    expect(Auth::guard('web')->attempt([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]))->toBeFalse();
});
