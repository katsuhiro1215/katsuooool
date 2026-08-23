<?php

use App\Models\ToolEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $response = $this->get(route('mypage.index'));

    $response->assertRedirect(route('login'));
});

test('a non-premium logged-in user is redirected to the premium page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('mypage.index'));

    $response->assertRedirect(route('premium.index'));
});

test('a premium user sees only their own tool history', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_mypage',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $otherUser = User::factory()->create();

    ToolEvent::create(['user_id' => $user->id, 'tool_slug' => 'image-crop', 'event_type' => 'process']);
    ToolEvent::create(['user_id' => $otherUser->id, 'tool_slug' => 'image-crop', 'event_type' => 'process']);

    $response = $this->actingAs($user)->get(route('mypage.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('MyPage')
        ->has('toolEvents.data', 1)
        ->where('toolEvents.data.0.tool_slug', 'image-crop')
    );
});
