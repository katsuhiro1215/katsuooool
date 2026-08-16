<?php

use App\Models\Admin;

test('admin:create creates a new admin account', function () {
    $this->artisan('admin:create', [
        'name' => 'Katsuhiro',
        'email' => 'katsuhiro@example.com',
        'password' => 'password1234',
    ])->assertSuccessful();

    $admin = Admin::where('email', 'katsuhiro@example.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('Katsuhiro');
});

test('admin:create rejects a duplicate email', function () {
    Admin::create([
        'name' => 'Existing',
        'email' => 'katsuhiro@example.com',
        'password' => bcrypt('password1234'),
    ]);

    $this->artisan('admin:create', [
        'name' => 'Katsuhiro',
        'email' => 'katsuhiro@example.com',
        'password' => 'password1234',
    ])->assertFailed();

    expect(Admin::count())->toBe(1);
});
