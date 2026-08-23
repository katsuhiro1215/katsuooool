<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

test('a free user is rate-limited after 10 requests per minute', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('image-compress.process'), ['files' => [$file]]);
    }

    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => [$file]]);

    $response->assertStatus(429);
});

test('a premium user is not rate-limited until 30 requests per minute', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_ratelimit',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    for ($i = 0; $i < 15; $i++) {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $response = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('image-compress.process'), ['files' => [$file]]);

        expect($response->status())->not->toBe(429);
    }
});
