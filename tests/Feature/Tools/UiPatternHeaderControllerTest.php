<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-header page renders', function () {
    $response = $this->get(route('ui-pattern-header.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternHeader'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-header.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-header')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes header-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>Button</button>',
        'css' => 'button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'header',
        'title' => 'ヘッダーパターン',
        'html' => '<header><nav>Nav</nav></header>',
        'css' => 'header { display: block; }',
    ]);

    $response = $this->get(route('ui-pattern-header.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternHeader')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'ヘッダーパターン')
    );
});
