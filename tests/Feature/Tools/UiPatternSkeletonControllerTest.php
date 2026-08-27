<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-skeleton page renders', function () {
    $response = $this->get(route('ui-pattern-skeleton.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternSkeleton'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-skeleton.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-skeleton')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes skeleton-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>button</button>',
        'css' => '.button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'skeleton',
        'title' => 'スケルトンローダー',
        'html' => '<div class="skeleton"></div>',
        'css' => '.skeleton { background: #eee; }',
    ]);

    $response = $this->get(route('ui-pattern-skeleton.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternSkeleton')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'スケルトンローダー')
    );
});
