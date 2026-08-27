<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-modal page renders', function () {
    $response = $this->get(route('ui-pattern-modal.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternModal'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-modal.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-modal')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes modal-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>button</button>',
        'css' => '.button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'modal',
        'title' => 'シンプルモーダル',
        'html' => '<div class="modal">modal</div>',
        'css' => '.modal { position: fixed; }',
    ]);

    $response = $this->get(route('ui-pattern-modal.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternModal')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'シンプルモーダル')
    );
});
