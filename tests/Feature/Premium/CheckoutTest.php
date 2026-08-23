<?php

use App\Models\User;

test('guests are redirected to login when starting checkout', function () {
    $response = $this->post(route('premium.checkout', ['plan' => 'monthly']));

    $response->assertRedirect(route('login'));
});

test('an invalid plan is rejected with a validation error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('premium.checkout', ['plan' => 'lifetime']));

    $response->assertStatus(422);
});

test('checkout returns a 503 instead of a raw Stripe exception when the price id is not configured', function () {
    config(['services.stripe.price_monthly' => '']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('premium.checkout', ['plan' => 'monthly']));

    $response->assertStatus(503);
});

test('an already-premium user is redirected back to the premium page instead of checking out again', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test999',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->post(route('premium.checkout', ['plan' => 'monthly']));

    $response->assertRedirect(route('premium.index'));
});
