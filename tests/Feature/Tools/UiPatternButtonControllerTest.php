<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the show page only includes button-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'カードパターン',
        'html' => '<div>card</div>',
        'css' => '.card {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '斜線シャドウボタン',
        'html' => '<a href="#"><span>Button</span></a>',
        'css' => 'a { display: block; }',
    ]);

    $response = $this->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternButton')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', '斜線シャドウボタン')
    );
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-button.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-button')->where('event_type', 'process')->exists())->toBeTrue();
});

test('a non-premium viewer sees only a rendered srcdoc for a premium-only pattern, never the raw html or css', function () {
    $premiumPattern = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'プレミアムボタン',
        'html' => '<a href="#">Premium</a>',
        'css' => 'a { color: gold; }',
        'is_premium' => true,
    ]);

    $response = $this->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternButton')
        ->has('patterns', 1)
        ->where('patterns.0.id', $premiumPattern->id)
        ->where('patterns.0.locked', true)
        ->has('patterns.0.srcdoc')
        ->missing('patterns.0.html')
        ->missing('patterns.0.css')
    );
});

test('a premium subscriber sees the raw html and css for a premium-only pattern', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_premium',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $premiumPattern = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'プレミアムボタン',
        'html' => '<a href="#">Premium</a>',
        'css' => 'a { color: gold; }',
        'is_premium' => true,
    ]);

    $response = $this->actingAs($user)->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternButton')
        ->where('patterns.0.locked', false)
        ->where('patterns.0.html', $premiumPattern->html)
        ->where('patterns.0.css', $premiumPattern->css)
        ->missing('patterns.0.srcdoc')
    );
});

test('a free pattern is never locked regardless of the viewer', function () {
    $freePattern = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '無料ボタン',
        'html' => '<a href="#">Free</a>',
        'css' => 'a { color: blue; }',
    ]);

    $response = $this->get(route('ui-pattern-button.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('patterns.0.locked', false)
        ->where('patterns.0.html', $freePattern->html)
        ->where('patterns.0.css', $freePattern->css)
    );
});
