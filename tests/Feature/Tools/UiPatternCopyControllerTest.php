<?php

use App\Models\UiPatternSnippet;
use App\Models\User;

test('posting to the copy endpoint increments the snippet copy_count', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'テストボタン',
        'html' => '<a href="#">x</a>',
        'css' => 'a {}',
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));

    $response->assertSuccessful();
    expect($snippet->fresh()->copy_count)->toBe(1);
});

test('posting twice from the same ip on the same day only counts once', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'テストカード',
        'html' => '<div>x</div>',
        'css' => '.card {}',
    ]);

    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));

    expect($snippet->fresh()->copy_count)->toBe(1);
});

test('a guest cannot copy-track a premium-locked pattern', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'プレミアムボタン',
        'html' => '<a href="#">premium</a>',
        'css' => 'a {}',
        'is_premium' => true,
    ]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));

    $response->assertNotFound();
    expect($snippet->fresh()->copy_count)->toBe(0);
});

test('a premium subscriber can copy-track a premium-locked pattern', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'プレミアムボタン',
        'html' => '<a href="#">premium</a>',
        'css' => 'a {}',
        'is_premium' => true,
    ]);

    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_ui_pattern_copy',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-snippets.copy', $snippet));

    $response->assertSuccessful();
    expect($snippet->fresh()->copy_count)->toBe(1);
});
