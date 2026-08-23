<?php

use App\Models\User;

test('guests are redirected to login', function () {
    $response = $this->post(route('premium.billing-portal'));

    $response->assertRedirect(route('login'));
});

test('a non-premium user cannot open the billing portal', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('premium.billing-portal'));

    $response->assertForbidden();
});
