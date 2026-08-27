<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-form-check page renders', function () {
    $response = $this->get(route('ui-pattern-form-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternFormCheck'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-form-check.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-form-check')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes form-check-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>Button</button>',
        'css' => 'button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'form-check',
        'title' => 'チェックボックスパターン',
        'html' => '<label><input type="checkbox">Check</label>',
        'css' => 'label { display: block; }',
    ]);

    $response = $this->get(route('ui-pattern-form-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternFormCheck')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'チェックボックスパターン')
    );
});
