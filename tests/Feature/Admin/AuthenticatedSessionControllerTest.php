<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

test('the admin login page can be rendered', function () {
    $response = $this->get(route('admin.login'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('admin/Login'));
});

test('an admin can log in with correct credentials', function () {
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated('admin');
});

test('an admin cannot log in with an incorrect password', function () {
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest('admin');
});

test('logging in as an admin does not authenticate the web guard', function () {
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'password1234',
    ]);

    $this->assertGuest('web');
});

test('an already-authenticated admin visiting the login page is redirected to the admin dashboard, not the user dashboard', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.login'));

    $response->assertRedirect(route('admin.dashboard'));
});

test('an authenticated admin can log out', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
});

test('login attempts are rate limited after repeated failures', function () {
    // Laravel's throttle middleware hashes the rate-limit cache key by
    // default (ThrottleRequests::$shouldHashKeys), so pre-clearing a
    // hand-constructed key string is unreliable. Use an email unique to
    // this test instead, so its throttle key can't collide with attempts
    // made by any other test in this file against the same cache store.
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'ratelimit-admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('admin.login.store'), [
            'email' => 'ratelimit-admin@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('admin.login.store'), [
        'email' => 'ratelimit-admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
});
