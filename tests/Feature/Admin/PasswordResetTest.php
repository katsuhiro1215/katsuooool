<?php

use App\Models\Admin;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('the forgot-password page renders', function () {
    $response = $this->get(route('admin.password.request'));

    $response->assertSuccessful();
});

test('a reset link is sent for an existing admin', function () {
    Notification::fake();

    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'reset-flow@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class);
});

test('requesting a reset link for a non-existent email does not reveal whether the account exists', function () {
    Notification::fake();

    $response = $this->post(route('admin.password.email'), ['email' => 'nobody@example.com']);

    $response->assertSessionHasNoErrors();
});

test('the reset-password page renders with a token', function () {
    $response = $this->get(route('admin.password.reset', ['token' => 'dummy-token']));

    $response->assertSuccessful();
});

test('a password can be reset with a valid token and meets the admin password policy', function () {
    Notification::fake();

    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'reset-success@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
        $response = $this->post(route('admin.password.store'), [
            'token' => $notification->token,
            'email' => $admin->email,
            'password' => 'N3w!SecurePassword',
            'password_confirmation' => 'N3w!SecurePassword',
        ]);

        $response->assertRedirect(route('admin.login'));

        return true;
    });

    expect(Hash::check('N3w!SecurePassword', $admin->fresh()->password))->toBeTrue();
});

test('resetting to a password that fails the admin policy is rejected', function () {
    Notification::fake();

    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'reset-weak@example.com',
        'password' => Hash::make('Str0ng!Passw0rd'),
    ]);

    $this->post(route('admin.password.email'), ['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('admin.password.store'), [
                'token' => $notification->token,
                'email' => $admin->email,
                'password' => 'nosymbol123',
                'password_confirmation' => 'nosymbol123',
            ]);

        $response->assertStatus(422);

        return true;
    });
});
