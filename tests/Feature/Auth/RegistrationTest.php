<?php

use App\Models\User;

test('a normal registration creates a user and redirects to the dashboard', function () {
    $response = $this->post(route('register'), [
        'name' => 'Taro Yamada',
        'email' => 'taro@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    expect(User::where('email', 'taro@example.com')->exists())->toBeTrue();
});

test('opting into premium without a plan or terms agreement fails validation', function () {
    $response = $this->from(route('register'))->post(route('register'), [
        'name' => 'Taro Yamada',
        'email' => 'taro@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'want_premium' => true,
    ]);

    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors(['plan', 'terms_agree']);
    expect(User::where('email', 'taro@example.com')->exists())->toBeFalse();
});

test('opting into premium with a plan and terms agreement creates the user and starts checkout', function () {
    // Test env has no Stripe price configured, so checkout aborts with 503 -
    // matching the existing guard behavior tested in Premium/CheckoutTest.php.
    $response = $this->post(route('register'), [
        'name' => 'Taro Yamada',
        'email' => 'taro@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'want_premium' => true,
        'plan' => 'monthly',
        'terms_agree' => true,
    ]);

    $response->assertStatus(503);
    expect(User::where('email', 'taro@example.com')->exists())->toBeTrue();
});
