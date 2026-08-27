<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-toast page renders', function () {
    $response = $this->get(route('ui-pattern-toast.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternToast'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-toast.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-toast')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes toast-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>button</button>',
        'css' => '.button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'toast',
        'title' => 'シンプルトースト',
        'html' => '<div class="toast">toast</div>',
        'css' => '.toast { position: fixed; }',
    ]);

    $response = $this->get(route('ui-pattern-toast.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternToast')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'シンプルトースト')
    );
});
