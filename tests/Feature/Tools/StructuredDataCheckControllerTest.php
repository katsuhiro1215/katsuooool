<?php

use App\Models\ToolEvent;
use App\Services\Tools\UrlFetchException;
use App\Services\Tools\UrlFetchService;

test('the structured-data-check page renders', function () {
    $response = $this->get(route('structured-data-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('StructuredDataCheck'));
});

test('checking a URL with valid JSON-LD returns the parsed block', function () {
    $html = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article"}</script>';

    $this->mock(UrlFetchService::class, function ($mock) use ($html) {
        $mock->shouldReceive('fetch')->once()->andReturn($html);
    });

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('structured-data-check.check'), ['url' => 'https://example.com/']);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('blocks.0.valid', true);
    $response->assertJsonPath('blocks.0.warnings', []);

    expect(ToolEvent::where('tool_slug', 'structured-data-check')->where('event_type', 'process')->exists())->toBeTrue();
});

test('a timeout from UrlFetchService returns a 422 with the failure reason', function () {
    $this->mock(UrlFetchService::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andThrow(UrlFetchException::timeout());
    });

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('structured-data-check.check'), ['url' => 'https://slow.example.com/']);

    $response->assertStatus(422);
    $response->assertJsonPath('reason', 'timeout');

    expect(ToolEvent::where('tool_slug', 'structured-data-check')->where('event_type', 'failed')->exists())->toBeTrue();
});
