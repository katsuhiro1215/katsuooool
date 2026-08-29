<?php

use App\Models\User;

test('the premium page renders for guests', function () {
    $response = $this->get(route('premium.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Premium')
        ->where('isPremium', false)
    );
});

test('the premium page reports enabled from config when premium is on', function () {
    config(['premium.enabled' => true]);

    $response = $this->get(route('premium.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Premium')
        ->where('enabled', true)
    );
});

test('the premium page reports disabled from config when premium is off', function () {
    config(['premium.enabled' => false]);

    $response = $this->get(route('premium.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Premium')
        ->where('enabled', false)
    );
});

test('the premium page reports true for an already-premium user', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test789',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('premium.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Premium')
        ->where('isPremium', true)
    );
});
