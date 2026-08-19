<?php

use App\Services\Tools\Dns\DnsResolver;
use Tests\TestCase;

uses(TestCase::class);
use App\Services\Tools\UrlFetchException;
use App\Services\Tools\UrlFetchService;
use Illuminate\Support\Facades\Http;

function fakeResolver(array $ips): DnsResolver
{
    return new class($ips) implements DnsResolver
    {
        public function __construct(private array $ips) {}

        public function resolve(string $host): array
        {
            return $this->ips;
        }
    };
}

test('fetches HTML successfully for a public host', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><body>ok</body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
    ]);

    $service = new UrlFetchService(fakeResolver(['93.184.216.34']));

    $html = $service->fetch('https://example.com/page');

    expect($html)->toContain('ok');
});

test('rejects a URL whose scheme is not http or https', function () {
    $service = new UrlFetchService(fakeResolver(['93.184.216.34']));

    expect(fn () => $service->fetch('file:///etc/passwd'))
        ->toThrow(UrlFetchException::class);

    try {
        $service->fetch('ftp://example.com/');
    } catch (UrlFetchException $e) {
        expect($e->reason)->toBe('invalid_url');
    }
});

test('rejects a host that resolves to a private IP address', function () {
    Http::fake();

    $service = new UrlFetchService(fakeResolver(['10.0.0.5']));

    try {
        $service->fetch('https://internal.example.com/');
        $this->fail('Expected UrlFetchException');
    } catch (UrlFetchException $e) {
        expect($e->reason)->toBe('blocked_host');
    }
});

test('rejects when any resolved IP is blocked even if another is public', function () {
    Http::fake();

    $service = new UrlFetchService(fakeResolver(['8.8.8.8', '127.0.0.1']));

    try {
        $service->fetch('https://mixed.example.com/');
        $this->fail('Expected UrlFetchException');
    } catch (UrlFetchException $e) {
        expect($e->reason)->toBe('blocked_host');
    }
});

test('rejects non-HTML content types', function () {
    Http::fake([
        'example.com/*' => Http::response('{"a":1}', 200, ['Content-Type' => 'application/json']),
    ]);

    $service = new UrlFetchService(fakeResolver(['93.184.216.34']));

    try {
        $service->fetch('https://example.com/data.json');
        $this->fail('Expected UrlFetchException');
    } catch (UrlFetchException $e) {
        expect($e->reason)->toBe('non_html');
    }
});

test('rejects a response body larger than the 2MB cap', function () {
    Http::fake([
        'example.com/*' => Http::response(str_repeat('a', 2 * 1024 * 1024 + 1), 200, ['Content-Type' => 'text/html']),
    ]);

    $service = new UrlFetchService(fakeResolver(['93.184.216.34']));

    try {
        $service->fetch('https://example.com/huge');
        $this->fail('Expected UrlFetchException');
    } catch (UrlFetchException $e) {
        expect($e->reason)->toBe('too_large');
    }
});

test('follows a redirect and re-validates the new host', function () {
    Http::fake([
        'example.com/old' => Http::response('', 302, ['Location' => 'https://example.com/new']),
        'example.com/new' => Http::response('<html>moved</html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $service = new UrlFetchService(fakeResolver(['93.184.216.34']));

    $html = $service->fetch('https://example.com/old');

    expect($html)->toContain('moved');
});

test('rejects a redirect chain longer than 3 hops', function () {
    Http::fake([
        'example.com/1' => Http::response('', 302, ['Location' => 'https://example.com/2']),
        'example.com/2' => Http::response('', 302, ['Location' => 'https://example.com/3']),
        'example.com/3' => Http::response('', 302, ['Location' => 'https://example.com/4']),
        'example.com/4' => Http::response('', 302, ['Location' => 'https://example.com/5']),
    ]);

    $service = new UrlFetchService(fakeResolver(['93.184.216.34']));

    try {
        $service->fetch('https://example.com/1');
        $this->fail('Expected UrlFetchException');
    } catch (UrlFetchException $e) {
        expect($e->reason)->toBe('too_many_redirects');
    }
});

test('surfaces a non-2xx HTTP status as http_error', function () {
    Http::fake([
        'example.com/missing' => Http::response('not found', 404, ['Content-Type' => 'text/html']),
    ]);

    $service = new UrlFetchService(fakeResolver(['93.184.216.34']));

    try {
        $service->fetch('https://example.com/missing');
        $this->fail('Expected UrlFetchException');
    } catch (UrlFetchException $e) {
        expect($e->reason)->toBe('http_error');
    }
});
