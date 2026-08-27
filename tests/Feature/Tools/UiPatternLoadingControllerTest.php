<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-loading page renders', function () {
    $response = $this->get(route('ui-pattern-loading.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternLoading'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-loading.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-loading')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes loading-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>button</button>',
        'css' => '.button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'loading',
        'title' => 'スピナー',
        'html' => '<div class="spinner"></div>',
        'css' => '.spinner { animation: spin 1s linear infinite; }',
    ]);

    $response = $this->get(route('ui-pattern-loading.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternLoading')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'スピナー')
    );
});
