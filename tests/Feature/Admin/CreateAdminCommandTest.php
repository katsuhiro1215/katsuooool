<?php

use App\Models\Admin;

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

test('admin:create rejects a password without a symbol', function () {
    $this->artisan('admin:create', [
        'name' => 'Katsuhiro',
        'email' => 'weak-password@example.com',
        'password' => 'NoSymbolHere123',
    ])->assertFailed();

    expect(Admin::where('email', 'weak-password@example.com')->exists())->toBeFalse();
});
