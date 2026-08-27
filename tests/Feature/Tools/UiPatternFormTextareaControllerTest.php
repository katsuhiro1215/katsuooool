<?php

use App\Models\ToolEvent;
use App\Models\UiPatternSnippet;
use Inertia\Testing\AssertableInertia as Assert;

test('the ui-pattern-form-textarea page renders', function () {
    $response = $this->get(route('ui-pattern-form-textarea.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('UiPatternFormTextarea'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ui-pattern-form-textarea.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'ui-pattern-form-textarea')->where('event_type', 'process')->exists())->toBeTrue();
});

test('the show page only includes form-textarea-category patterns ordered by id', function () {
    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'ボタンパターン',
        'html' => '<button>button</button>',
        'css' => '.button {}',
    ]);

    $first = UiPatternSnippet::create([
        'category' => 'form-textarea',
        'title' => 'オートリサイズテキストエリア',
        'html' => '<textarea placeholder="Message"></textarea>',
        'css' => 'textarea { border: 1px solid #ccc; }',
    ]);

    $response = $this->get(route('ui-pattern-form-textarea.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('UiPatternFormTextarea')
        ->has('patterns', 1)
        ->where('patterns.0.id', $first->id)
        ->where('patterns.0.title', 'オートリサイズテキストエリア')
    );
});
