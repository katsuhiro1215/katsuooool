<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-footer page renders', function () {
    $response = $this->get(route('ui-pattern-footer.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternFooter'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-footer.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-footer')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes footer-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>Button</button>',
        'css' => 'button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'footer',
        'title' => 'フッターパターン',
        'html' => '<footer>Footer</footer>',
        'css' => 'footer { display: block; }',
    ]);

    $response = $this->get(route('ui-pattern-footer.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternFooter')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'フッターパターン')
    );
});
