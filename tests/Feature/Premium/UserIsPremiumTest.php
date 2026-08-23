<?php

use App\Models\User;

test('a user with no subscription is not premium', function () {
    $user = User::factory()->create();

    expect($user->isPremium())->toBeFalse();
});

test('a user with an active default subscription is premium', function () {
    $user = User::factory()->create();

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    expect($user->isPremium())->toBeTrue();
});

test('a user whose subscription has fully ended is not premium', function () {
    $user = User::factory()->create();

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test456',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
        'ends_at' => now()->subDay(),
    ]);

    expect($user->isPremium())->toBeFalse();
});
