<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-card page renders', function () {
    $response = $this->get(route('ui-pattern-card.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternCard'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-card.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-card')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes card-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>button</button>',
        'css' => '.button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'card',
        'title' => 'シンプルカード',
        'html' => '<div class="card">card</div>',
        'css' => '.card { border-radius: 8px; }',
    ]);

    $response = $this->get(route('ui-pattern-card.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternCard')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'シンプルカード')
    );
});
