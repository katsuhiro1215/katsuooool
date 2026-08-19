<?php

use App\Models\ToolEvent;
use App\Services\Tools\UrlFetchException;
use App\Services\Tools\UrlFetchService;

test('the ogp-check page renders', function () {
    $response = $this->get(route('ogp-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('OgpCheck'));
});

test('checking a valid URL returns the parsed OGP tags', function () {
    $html = '<html><head>'
        .'<meta property="og:title" content="タイトル">'
        .'<meta property="og:description" content="説明">'
        .'<meta property="og:image" content="https://example.com/a.png">'
        .'<meta property="og:url" content="https://example.com/">'
        .'</head></html>';

    $this->mock(UrlFetchService::class, function ($mock) use ($html) {
        $mock->shouldReceive('fetch')->once()->andReturn($html);
    });

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ogp-check.check'), ['url' => 'https://example.com/']);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('tags.og:title', 'タイトル');
    $response->assertJsonPath('missing_recommended', []);

    expect(ToolEvent::where('tool_slug', 'ogp-check')->where('event_type', 'process')->exists())->toBeTrue();
});

test('a blocked host returns a 422 with the failure reason', function () {
    $this->mock(UrlFetchService::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andThrow(UrlFetchException::blockedHost());
    });

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ogp-check.check'), ['url' => 'https://internal.example.com/']);

    $response->assertStatus(422);
    $response->assertJsonPath('status', 'failed');
    $response->assertJsonPath('reason', 'blocked_host');
});

test('an invalid URL fails validation before UrlFetchService is called', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ogp-check.check'), ['url' => 'not-a-url']);

    $response->assertStatus(422);
});
