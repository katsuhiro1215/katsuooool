# SEOカテゴリ(Phase 9)Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** SEOカテゴリの4ツール(title文字数チェック・description文字数チェック・OGPチェック・構造化データ確認)を実装し、SSRF対策済みの共通URL取得基盤(`UrlFetchService`)を新設する。

**Architecture:** title/description文字数チェックは既存のColor/Webテキスト系と同じ「`TrackedToolController`継承 + クライアント完結」パターン。OGPチェック・構造化データ確認は新規の「URL入力 → サーバーが`UrlFetchService`でHTML取得 → 専用Serviceがパース → JSON返却」パターンで、`UrlFetchService`はDNS解決結果のIPアドレスをprivate/reserved判定でブロックし(PHPの`FILTER_VALIDATE_IP`組み込みフラグを利用)、検証済みIPに直接接続する(ホスト名再解決によるDNS rebindingを防止)。

**Tech Stack:** PHP 8.3 / Laravel 13(`Illuminate\Support\Facades\Http`) / Vue 3 + TypeScript / Vitest / Pest 4

**Spec:** [SPEC.md](../../../SPEC.md) §23(SEOカテゴリツール仕様 Phase 9)

## Global Constraints

- ドキュメント成果物・コード内の文言はすべて日本語(CLAUDE.md)。
- 本番はXserver共有サーバー。`exec()`・シェルコマンド実行・常駐キューワーカーは使用不可。すべて同期処理(SPEC.md §2)。
- `UrlFetchService`許可スキーム: `http`/`https`のみ(SPEC.md §23.3)。
- ブロック対象IP: ループバック・プライベート・リンクローカル(クラウドmetadata含む)・その他予約済みレンジ・IPv6ユニークローカル(SPEC.md §23.3)。
- DNS rebinding対策: 検証済みIPへ直接接続すること(SPEC.md §23.3)。
- リダイレクトは自動追従せず、毎回同じ検証を再実行、最大3回まで(SPEC.md §23.3)。
- タイムアウト: 接続5秒/合計10秒。レスポンスサイズ上限2MB。`Content-Type: text/html`以外は拒否(SPEC.md §23.3)。
- 固定User-Agentを付与し、Cookie・認証情報は送信しない(SPEC.md §23.3)。
- URLフェッチ系エンドポイントには悪用防止のためLaravelの`throttle`ミドルウェアを適用(SPEC.md §23.3)。
- title推奨文字数: 全角30〜35字目安。description推奨文字数: 160字目安(モバイル120字/デスクトップ160字、SPEC.md §23.1/23.2)。
- 構造化データ確認のv1スコープはJSON-LDのみ(Microdata/RDFaは対象外、SPEC.md §23.5)。
- PHPStan level 7(`composer.json`の`types:check`)、ESLint/Prettier/vue-tsc(`npm run lint:check`/`format:check`/`types:check`)がすべてグリーンであること。

---

## 参考: 既存パターン

- `App\Http\Controllers\Tools\Concerns\TrackedToolController`(`app/Http/Controllers/Tools/Concerns/TrackedToolController.php`): `show()`でInertiaページ表示、`track()`で`ToolEventLogger::log(eventType: 'process')`のみ記録。クライアント完結ツールはこれを継承する。
- `App\Services\Tools\ToolEventLogger::log(string $toolSlug, string $eventType, array $data = [], ?string $ip = null, ?string $userAgent = null): ToolEvent`
- `config/tools.php`: `slug`/`name`/`category`/`description`/`href`の配列。
- `routes/web.php`: 各ツールの`GET`(表示)+`POST`(処理 or track)ルートを追記。
- `tests/Feature/Admin/DashboardControllerTest.php:46`: `->has('stats', 17)` — ツール数が増えるたびにこの数値を更新する。

---

### Task 1: UrlFetchService基盤(DNS解決・IPブロック判定・SSRF対策fetch)

**Files:**
- Create: `app/Services/Tools/Dns/DnsResolver.php`
- Create: `app/Services/Tools/Dns/SystemDnsResolver.php`
- Create: `app/Services/Tools/Dns/IpGuard.php`
- Create: `app/Services/Tools/UrlFetchException.php`
- Create: `app/Services/Tools/UrlFetchService.php`
- Modify: `app/Providers/AppServiceProvider.php`(`register()`に`DnsResolver`のバインドを追加)
- Test: `tests/Unit/Services/Tools/IpGuardTest.php`
- Test: `tests/Unit/Services/Tools/UrlFetchServiceTest.php`

**Interfaces:**
- Consumes: なし(このタスクが基盤)。
- Produces:
  - `App\Services\Tools\Dns\DnsResolver::resolve(string $host): array`(戻り値は`list<string>`、IPv4/IPv6混在可)
  - `App\Services\Tools\Dns\IpGuard::isBlocked(string $ip): bool`(static)
  - `App\Services\Tools\UrlFetchException`: `public readonly string $reason`。静的コンストラクタ: `invalidUrl()`, `blockedHost()`, `timeout()`, `tooLarge()`, `nonHtml()`, `httpError(int $status)`, `tooManyRedirects()`。すべて`self`を返す。
  - `App\Services\Tools\UrlFetchService::__construct(DnsResolver $dnsResolver)`
  - `App\Services\Tools\UrlFetchService::fetch(string $url): string`(HTML本文を返す。失敗時は`UrlFetchException`を投げる)

- [ ] **Step 1: `IpGuard`の失敗するテストを書く**

```php
<?php

use App\Services\Tools\Dns\IpGuard;

test('blocks loopback addresses', function () {
    expect(IpGuard::isBlocked('127.0.0.1'))->toBeTrue();
    expect(IpGuard::isBlocked('::1'))->toBeTrue();
});

test('blocks private ranges', function () {
    expect(IpGuard::isBlocked('10.0.0.1'))->toBeTrue();
    expect(IpGuard::isBlocked('172.16.0.1'))->toBeTrue();
    expect(IpGuard::isBlocked('192.168.1.1'))->toBeTrue();
});

test('blocks link-local addresses including the cloud metadata endpoint', function () {
    expect(IpGuard::isBlocked('169.254.169.254'))->toBeTrue();
    expect(IpGuard::isBlocked('169.254.1.1'))->toBeTrue();
});

test('blocks IPv6 unique local addresses', function () {
    expect(IpGuard::isBlocked('fc00::1'))->toBeTrue();
    expect(IpGuard::isBlocked('fd12:3456:789a::1'))->toBeTrue();
});

test('allows public IPv4 and IPv6 addresses', function () {
    expect(IpGuard::isBlocked('8.8.8.8'))->toBeFalse();
    expect(IpGuard::isBlocked('2001:4860:4860::8888'))->toBeFalse();
});

test('blocks a malformed IP string', function () {
    expect(IpGuard::isBlocked('not-an-ip'))->toBeTrue();
});
```

- [ ] **Step 2: テストが失敗することを確認**

Run: `php artisan test tests/Unit/Services/Tools/IpGuardTest.php`
Expected: FAIL(`IpGuard`クラスが存在しない)

- [ ] **Step 3: `IpGuard`を実装**

```php
<?php

namespace App\Services\Tools\Dns;

class IpGuard
{
    public static function isBlocked(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `php artisan test tests/Unit/Services/Tools/IpGuardTest.php`
Expected: PASS(6テスト)

- [ ] **Step 5: `DnsResolver`インターフェースと`SystemDnsResolver`を実装**

```php
<?php

namespace App\Services\Tools\Dns;

interface DnsResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array;
}
```

```php
<?php

namespace App\Services\Tools\Dns;

class SystemDnsResolver implements DnsResolver
{
    public function resolve(string $host): array
    {
        $ips = [];

        $v4 = gethostbynamel($host);

        if ($v4 !== false) {
            $ips = array_merge($ips, $v4);
        }

        $v6Records = @dns_get_record($host, DNS_AAAA);

        if (is_array($v6Records)) {
            foreach ($v6Records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
```

- [ ] **Step 6: `AppServiceProvider`に`DnsResolver`のバインドを追加**

`app/Providers/AppServiceProvider.php`の`register()`メソッドを以下に変更:

```php
    public function register(): void
    {
        $this->app->bind(\App\Services\Tools\Dns\DnsResolver::class, \App\Services\Tools\Dns\SystemDnsResolver::class);
    }
```

- [ ] **Step 7: `UrlFetchException`を実装**

```php
<?php

namespace App\Services\Tools;

use RuntimeException;

class UrlFetchException extends RuntimeException
{
    private function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalidUrl(): self
    {
        return new self('invalid_url', '指定されたURLが正しくありません。');
    }

    public static function blockedHost(): self
    {
        return new self('blocked_host', 'アクセスが許可されていないホストです。');
    }

    public static function timeout(): self
    {
        return new self('timeout', '取得がタイムアウトしました。');
    }

    public static function tooLarge(): self
    {
        return new self('too_large', 'レスポンスサイズが上限(2MB)を超えています。');
    }

    public static function nonHtml(): self
    {
        return new self('non_html', 'HTML以外のコンテンツは解析できません。');
    }

    public static function httpError(int $status): self
    {
        return new self('http_error', "取得に失敗しました(HTTP {$status})。");
    }

    public static function tooManyRedirects(): self
    {
        return new self('too_many_redirects', 'リダイレクトが多すぎます。');
    }
}
```

- [ ] **Step 8: `UrlFetchService`の失敗するテストを書く**

`config/tools.php`は変更しないこと(このテストファイルは`tests/Unit/Services/Tools/UrlFetchServiceTest.php`)。`DnsResolver`はテスト用のインライン無名クラスで差し替え、`Http::fake()`で実ネットワークを使わない。

```php
<?php

use App\Services\Tools\Dns\DnsResolver;
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
```

- [ ] **Step 9: テストが失敗することを確認**

Run: `php artisan test tests/Unit/Services/Tools/UrlFetchServiceTest.php`
Expected: FAIL(`UrlFetchService`クラスが存在しない)

- [ ] **Step 10: `UrlFetchService`を実装**

```php
<?php

namespace App\Services\Tools;

use App\Services\Tools\Dns\DnsResolver;
use App\Services\Tools\Dns\IpGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class UrlFetchService
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const MAX_REDIRECTS = 3;

    private const MAX_RESPONSE_BYTES = 2 * 1024 * 1024;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const TOTAL_TIMEOUT_SECONDS = 10;

    private const USER_AGENT = 'katsuooool-bot/1.0 (+https://katsuooool.katsucode.jp)';

    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function __construct(private readonly DnsResolver $dnsResolver) {}

    public function fetch(string $url): string
    {
        $currentUrl = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $ip = $this->resolveAndValidate($currentUrl);
            $response = $this->request($currentUrl, $ip);

            if (in_array($response->status(), self::REDIRECT_STATUSES, true)) {
                if ($hop === self::MAX_REDIRECTS) {
                    throw UrlFetchException::tooManyRedirects();
                }

                $location = $response->header('Location');

                if ($location === '' || $location === null) {
                    throw UrlFetchException::httpError($response->status());
                }

                $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);

                continue;
            }

            if (! $response->successful()) {
                throw UrlFetchException::httpError($response->status());
            }

            $contentType = strtolower($response->header('Content-Type') ?? '');

            if (! str_contains($contentType, 'text/html')) {
                throw UrlFetchException::nonHtml();
            }

            $body = $response->body();

            if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                throw UrlFetchException::tooLarge();
            }

            return $body;
        }

        throw UrlFetchException::tooManyRedirects();
    }

    private function resolveAndValidate(string $url): string
    {
        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            throw UrlFetchException::invalidUrl();
        }

        if (! in_array(strtolower($parsed['scheme']), self::ALLOWED_SCHEMES, true)) {
            throw UrlFetchException::invalidUrl();
        }

        $host = $parsed['host'];

        $candidateIps = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->dnsResolver->resolve($host);

        if ($candidateIps === []) {
            throw UrlFetchException::invalidUrl();
        }

        foreach ($candidateIps as $ip) {
            if (IpGuard::isBlocked($ip)) {
                throw UrlFetchException::blockedHost();
            }
        }

        return $candidateIps[0];
    }

    private function request(string $url, string $ip): Response
    {
        $parsed = parse_url($url);
        $host = $parsed['host'];
        $scheme = strtolower($parsed['scheme']);
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

        try {
            return Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->withOptions([
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                    'allow_redirects' => false,
                ])
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TOTAL_TIMEOUT_SECONDS)
                ->get($url);
        } catch (ConnectionException) {
            throw UrlFetchException::timeout();
        }
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        $basePath = isset($base['path']) ? (preg_replace('#/[^/]*$#', '/', $base['path']) ?? '/') : '/';

        return "{$scheme}://{$host}{$port}{$basePath}{$location}";
    }
}
```

- [ ] **Step 11: テストが通ることを確認**

Run: `php artisan test tests/Unit/Services/Tools/UrlFetchServiceTest.php`
Expected: PASS(9テスト)

- [ ] **Step 12: PHPStanとPintを確認**

Run: `composer types:check && composer lint:check`
Expected: どちらもエラーなし

- [ ] **Step 13: コミット**

```bash
git add app/Services/Tools/Dns app/Services/Tools/UrlFetchException.php app/Services/Tools/UrlFetchService.php app/Providers/AppServiceProvider.php tests/Unit/Services/Tools
git commit -m "feat: add SSRF-safe UrlFetchService for SEO tools"
```

---

### Task 2: OgpCheckService(OGP/Twitter Cardタグ解析)

**Files:**
- Create: `app/Services/Tools/OgpCheckService.php`
- Test: `tests/Unit/Services/Tools/OgpCheckServiceTest.php`

**Interfaces:**
- Consumes: なし(HTML文字列を直接受け取る。Task 1の`UrlFetchService::fetch()`が返す文字列がController経由で渡される想定)。
- Produces: `App\Services\Tools\OgpCheckService::analyze(string $html): array{tags: array<string,string>, missing_recommended: list<string>}`

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Services\Tools\OgpCheckService;

test('extracts og and twitter meta tags', function () {
    $html = <<<'HTML'
        <html><head>
            <meta property="og:title" content="サンプルタイトル">
            <meta property="og:description" content="説明文">
            <meta property="og:image" content="https://example.com/img.png">
            <meta property="og:url" content="https://example.com/">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="description" content="無関係なタグ">
        </head><body></body></html>
        HTML;

    $result = (new OgpCheckService)->analyze($html);

    expect($result['tags'])->toBe([
        'og:title' => 'サンプルタイトル',
        'og:description' => '説明文',
        'og:image' => 'https://example.com/img.png',
        'og:url' => 'https://example.com/',
        'twitter:card' => 'summary_large_image',
    ]);
    expect($result['missing_recommended'])->toBe([]);
});

test('reports missing recommended tags', function () {
    $html = '<html><head><meta property="og:title" content="タイトルのみ"></head></html>';

    $result = (new OgpCheckService)->analyze($html);

    expect($result['tags'])->toBe(['og:title' => 'タイトルのみ']);
    expect($result['missing_recommended'])->toBe(['og:description', 'og:image', 'og:url']);
});

test('returns empty tags for HTML with no relevant meta tags', function () {
    $result = (new OgpCheckService)->analyze('<html><head><title>No OGP</title></head></html>');

    expect($result['tags'])->toBe([]);
    expect($result['missing_recommended'])->toBe(['og:title', 'og:description', 'og:image', 'og:url']);
});
```

- [ ] **Step 2: テストが失敗することを確認**

Run: `php artisan test tests/Unit/Services/Tools/OgpCheckServiceTest.php`
Expected: FAIL(`OgpCheckService`クラスが存在しない)

- [ ] **Step 3: `OgpCheckService`を実装**

```php
<?php

namespace App\Services\Tools;

use DOMDocument;

class OgpCheckService
{
    private const RECOMMENDED = ['og:title', 'og:description', 'og:image', 'og:url'];

    /**
     * @return array{tags: array<string, string>, missing_recommended: list<string>}
     */
    public function analyze(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $tags = [];

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $property = $meta->getAttribute('property') ?: $meta->getAttribute('name');

            if ($property === '' || (! str_starts_with($property, 'og:') && ! str_starts_with($property, 'twitter:'))) {
                continue;
            }

            $tags[$property] = $meta->getAttribute('content');
        }

        $missing = array_values(array_filter(
            self::RECOMMENDED,
            fn (string $key): bool => ! array_key_exists($key, $tags),
        ));

        return ['tags' => $tags, 'missing_recommended' => $missing];
    }
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `php artisan test tests/Unit/Services/Tools/OgpCheckServiceTest.php`
Expected: PASS(3テスト)

- [ ] **Step 5: コミット**

```bash
git add app/Services/Tools/OgpCheckService.php tests/Unit/Services/Tools/OgpCheckServiceTest.php
git commit -m "feat: add OgpCheckService to parse OGP/Twitter Card meta tags"
```

---

### Task 3: StructuredDataCheckService(JSON-LD解析)

**Files:**
- Create: `app/Services/Tools/StructuredDataCheckService.php`
- Test: `tests/Unit/Services/Tools/StructuredDataCheckServiceTest.php`

**Interfaces:**
- Consumes: なし(HTML文字列を直接受け取る)。
- Produces: `App\Services\Tools\StructuredDataCheckService::analyze(string $html): list<array{raw: string, valid: bool, data?: mixed, error?: string, warnings: list<string>}>`

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Services\Tools\StructuredDataCheckService;

test('parses a valid JSON-LD block with no warnings', function () {
    $html = <<<'HTML'
        <html><head>
        <script type="application/ld+json">
        {"@context":"https://schema.org","@type":"Article","headline":"見出し"}
        </script>
        </head></html>
        HTML;

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result)->toHaveCount(1);
    expect($result[0]['valid'])->toBeTrue();
    expect($result[0]['data'])->toBe(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => '見出し']);
    expect($result[0]['warnings'])->toBe([]);
});

test('flags a block missing @context and @type', function () {
    $html = '<script type="application/ld+json">{"headline":"見出しのみ"}</script>';

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result[0]['valid'])->toBeTrue();
    expect($result[0]['warnings'])->toBe(['@context が見つかりません。', '@type が見つかりません。']);
});

test('reports a JSON syntax error without crashing', function () {
    $html = '<script type="application/ld+json">{invalid json}</script>';

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result[0]['valid'])->toBeFalse();
    expect($result[0]['error'])->not->toBeEmpty();
});

test('ignores script tags that are not application/ld+json', function () {
    $html = '<script type="application/javascript">console.log(1);</script>';

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result)->toBe([]);
});

test('handles a top-level JSON-LD array of multiple entities', function () {
    $html = <<<'HTML'
        <script type="application/ld+json">
        [
            {"@context":"https://schema.org","@type":"Person","name":"A"},
            {"@type":"Organization","name":"B"}
        ]
        </script>
        HTML;

    $result = (new StructuredDataCheckService)->analyze($html);

    expect($result[0]['valid'])->toBeTrue();
    expect($result[0]['warnings'])->toBe(['@context が見つかりません。']);
});
```

- [ ] **Step 2: テストが失敗することを確認**

Run: `php artisan test tests/Unit/Services/Tools/StructuredDataCheckServiceTest.php`
Expected: FAIL(`StructuredDataCheckService`クラスが存在しない)

- [ ] **Step 3: `StructuredDataCheckService`を実装**

```php
<?php

namespace App\Services\Tools;

use DOMDocument;

class StructuredDataCheckService
{
    /**
     * @return list<array{raw: string, valid: bool, data?: mixed, error?: string, warnings: list<string>}>
     */
    public function analyze(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $results = [];

        foreach ($dom->getElementsByTagName('script') as $script) {
            if (strtolower($script->getAttribute('type')) !== 'application/ld+json') {
                continue;
            }

            $raw = trim($script->textContent);
            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $results[] = ['raw' => $raw, 'valid' => false, 'error' => json_last_error_msg(), 'warnings' => []];

                continue;
            }

            $entries = array_is_list($decoded) ? $decoded : [$decoded];
            $warnings = [];

            foreach ($entries as $entry) {
                if (! is_array($entry) || ! array_key_exists('@context', $entry)) {
                    $warnings[] = '@context が見つかりません。';
                }

                if (! is_array($entry) || ! array_key_exists('@type', $entry)) {
                    $warnings[] = '@type が見つかりません。';
                }
            }

            $results[] = [
                'raw' => $raw,
                'valid' => true,
                'data' => $decoded,
                'warnings' => array_values(array_unique($warnings)),
            ];
        }

        return $results;
    }
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `php artisan test tests/Unit/Services/Tools/StructuredDataCheckServiceTest.php`
Expected: PASS(5テスト)

- [ ] **Step 5: コミット**

```bash
git add app/Services/Tools/StructuredDataCheckService.php tests/Unit/Services/Tools/StructuredDataCheckServiceTest.php
git commit -m "feat: add StructuredDataCheckService to parse JSON-LD blocks"
```

---

### Task 4: title/description文字数チェック(クライアント完結2ツール)

**Files:**
- Create: `resources/js/lib/seoTextCheck.ts`
- Create: `resources/js/lib/seoTextCheck.test.ts`
- Create: `app/Http/Controllers/Tools/SeoTitleCheckController.php`
- Create: `app/Http/Controllers/Tools/SeoDescriptionCheckController.php`
- Create: `resources/js/pages/SeoTitleCheck.vue`
- Create: `resources/js/pages/SeoDescriptionCheck.vue`
- Test: `tests/Feature/Tools/SeoTitleCheckControllerTest.php`
- Test: `tests/Feature/Tools/SeoDescriptionCheckControllerTest.php`
- Modify: `routes/web.php`(2ツール分のルート追加)
- Modify: `config/tools.php`(2エントリ追加)

**Interfaces:**
- Consumes: `App\Http\Controllers\Tools\Concerns\TrackedToolController`(Task前提の既存クラス、`slug()`/`page()`を実装するだけでよい)。
- Produces: `checkTitleLength(text: string): SeoTextCheckResult`, `checkDescriptionLength(text: string): SeoTextCheckResult`(`SeoTextCheckResult = { characterCount: number; byteCount: number; withinRecommended: boolean }`)。後続タスクはこれらを使わない(独立ツール)。

- [ ] **Step 1: フロントの失敗するテストを書く**

```typescript
import { describe, expect, test } from 'vitest';

import { checkDescriptionLength, checkTitleLength } from './seoTextCheck';

describe('checkTitleLength', () => {
    test('counts characters and bytes, and flags within the recommended range', () => {
        const result = checkTitleLength('短いタイトル');

        expect(result.characterCount).toBe(6);
        expect(result.withinRecommended).toBe(true);
    });

    test('flags a title over 35 characters as outside the recommended range', () => {
        const result = checkTitleLength('あ'.repeat(36));

        expect(result.characterCount).toBe(36);
        expect(result.withinRecommended).toBe(false);
    });

    test('counts UTF-8 byte length correctly for multi-byte characters', () => {
        const result = checkTitleLength('あ');

        expect(result.byteCount).toBe(3);
    });

    test('counts surrogate-pair characters (e.g. emoji) as a single character', () => {
        const result = checkTitleLength('😀');

        expect(result.characterCount).toBe(1);
    });
});

describe('checkDescriptionLength', () => {
    test('flags a description within 160 characters as within the recommended range', () => {
        const result = checkDescriptionLength('あ'.repeat(160));

        expect(result.withinRecommended).toBe(true);
    });

    test('flags a description over 160 characters as outside the recommended range', () => {
        const result = checkDescriptionLength('あ'.repeat(161));

        expect(result.withinRecommended).toBe(false);
    });

    test('returns zero counts for an empty string', () => {
        const result = checkDescriptionLength('');

        expect(result.characterCount).toBe(0);
        expect(result.byteCount).toBe(0);
        expect(result.withinRecommended).toBe(true);
    });
});
```

- [ ] **Step 2: テストが失敗することを確認**

Run: `npm run test -- seoTextCheck`
Expected: FAIL(`./seoTextCheck`モジュールが存在しない)

- [ ] **Step 3: `seoTextCheck.ts`を実装**

```typescript
export interface SeoTextCheckResult {
    characterCount: number;
    byteCount: number;
    withinRecommended: boolean;
}

const TITLE_RECOMMENDED_MAX = 35;
const DESCRIPTION_RECOMMENDED_MAX = 160;

export function checkTitleLength(text: string): SeoTextCheckResult {
    return buildResult(text, TITLE_RECOMMENDED_MAX);
}

export function checkDescriptionLength(text: string): SeoTextCheckResult {
    return buildResult(text, DESCRIPTION_RECOMMENDED_MAX);
}

function buildResult(text: string, recommendedMax: number): SeoTextCheckResult {
    const characterCount = Array.from(text).length;
    const byteCount = new TextEncoder().encode(text).length;

    return {
        characterCount,
        byteCount,
        withinRecommended: characterCount <= recommendedMax,
    };
}
```

- [ ] **Step 4: テストが通ることを確認**

Run: `npm run test -- seoTextCheck`
Expected: PASS(7テスト)

- [ ] **Step 5: バックエンドControllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class SeoTitleCheckController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'seo-title-check';
    }

    protected function page(): string
    {
        return 'SeoTitleCheck';
    }
}
```

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class SeoDescriptionCheckController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'seo-description-check';
    }

    protected function page(): string
    {
        return 'SeoDescriptionCheck';
    }
}
```

- [ ] **Step 6: ルートを追加**

`routes/web.php`の`use`宣言に以下を追加(アルファベット順、既存の並びに合わせる):

```php
use App\Http\Controllers\Tools\SeoDescriptionCheckController;
use App\Http\Controllers\Tools\SeoTitleCheckController;
```

`require __DIR__.'/settings.php';`より前、`base64-encode`のルート群の直後に追加:

```php
Route::get('/seo-title-check', [SeoTitleCheckController::class, 'show'])->name('seo-title-check.show');
Route::post('/seo-title-check/track', [SeoTitleCheckController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('seo-title-check.track');

Route::get('/seo-description-check', [SeoDescriptionCheckController::class, 'show'])->name('seo-description-check.show');
Route::post('/seo-description-check/track', [SeoDescriptionCheckController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('seo-description-check.track');
```

- [ ] **Step 7: `config/tools.php`にエントリを追加**

配列の末尾(`base64-encode`エントリの後)に追加:

```php
    [
        'slug' => 'seo-title-check',
        'name' => 'title文字数チェック',
        'category' => 'seo',
        'description' => 'titleタグの文字数・バイト数をチェックし、検索結果での表示可否目安を判定します。',
        'href' => '/seo-title-check',
    ],
    [
        'slug' => 'seo-description-check',
        'name' => 'description文字数チェック',
        'category' => 'seo',
        'description' => 'meta descriptionの文字数・バイト数をチェックし、表示可否目安を判定します。',
        'href' => '/seo-description-check',
    ],
```

- [ ] **Step 8: バックエンドFeatureテストを書く**

```php
<?php

use App\Models\ToolEvent;

test('the seo-title-check page renders', function () {
    $response = $this->get(route('seo-title-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('SeoTitleCheck'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('seo-title-check.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'seo-title-check')->where('event_type', 'process')->exists())->toBeTrue();
});
```

```php
<?php

use App\Models\ToolEvent;

test('the seo-description-check page renders', function () {
    $response = $this->get(route('seo-description-check.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('SeoDescriptionCheck'));
});

test('posting to the track endpoint logs a process event', function () {
    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('seo-description-check.track'));

    $response->assertSuccessful();

    expect(ToolEvent::where('tool_slug', 'seo-description-check')->where('event_type', 'process')->exists())->toBeTrue();
});
```

- [ ] **Step 9: Inertiaページを作成(title)**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { checkTitleLength } from '@/lib/seoTextCheck';

const input = ref('');

const result = computed(() => checkTitleLength(input.value));

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(input, (value) => {
    if (value.trim() === '') {
        return;
    }

    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/seo-title-check/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="title文字数チェック - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    title文字数チェック
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    titleタグの文字列を貼り付けると、文字数・バイト数と検索結果での表示可否目安を確認できます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <label
                for="title-input"
                class="block text-sm font-medium text-gray-700"
                >title</label
            >
            <textarea
                id="title-input"
                v-model="input"
                rows="3"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                placeholder="ページのtitleを入力"
            />

            <div class="mt-4 flex gap-6 text-sm text-gray-700">
                <span>文字数: {{ result.characterCount }}</span>
                <span>バイト数: {{ result.byteCount }}</span>
            </div>

            <p
                class="mt-2 text-sm"
                :class="result.withinRecommended ? 'text-green-600' : 'text-amber-600'"
            >
                {{
                    result.withinRecommended
                        ? '推奨範囲(全角30〜35字程度)に収まっています。'
                        : 'Google検索結果で末尾が省略される可能性があります(推奨: 全角30〜35字程度)。'
                }}
            </p>
        </main>
    </div>
</template>
```

- [ ] **Step 10: Inertiaページを作成(description)**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

import { checkDescriptionLength } from '@/lib/seoTextCheck';

const input = ref('');

const result = computed(() => checkDescriptionLength(input.value));

let trackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(input, (value) => {
    if (value.trim() === '') {
        return;
    }

    if (trackTimeout) {
        clearTimeout(trackTimeout);
    }

    trackTimeout = setTimeout(() => {
        axios.post('/seo-description-check/track').catch(() => {
            // Analytics-only endpoint; ignore failures.
        });
    }, 500);
});
</script>

<template>
    <Head title="description文字数チェック - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    description文字数チェック
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    meta
                    descriptionの文字列を貼り付けると、文字数・バイト数と表示可否目安を確認できます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <label
                for="description-input"
                class="block text-sm font-medium text-gray-700"
                >description</label
            >
            <textarea
                id="description-input"
                v-model="input"
                rows="4"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                placeholder="ページのmeta descriptionを入力"
            />

            <div class="mt-4 flex gap-6 text-sm text-gray-700">
                <span>文字数: {{ result.characterCount }}</span>
                <span>バイト数: {{ result.byteCount }}</span>
            </div>

            <p
                class="mt-2 text-sm"
                :class="result.withinRecommended ? 'text-green-600' : 'text-amber-600'"
            >
                {{
                    result.withinRecommended
                        ? '推奨範囲(160字程度)に収まっています。'
                        : '検索結果で末尾が省略される可能性があります(目安: モバイル120字/デスクトップ160字程度)。'
                }}
            </p>
        </main>
    </div>
</template>
```

- [ ] **Step 11: すべてのテストが通ることを確認**

Run: `composer test && npm run test`
Expected: PASS(バックエンド・フロントとも全件グリーン)

- [ ] **Step 12: コミット**

```bash
git add resources/js/lib/seoTextCheck.ts resources/js/lib/seoTextCheck.test.ts app/Http/Controllers/Tools/SeoTitleCheckController.php app/Http/Controllers/Tools/SeoDescriptionCheckController.php resources/js/pages/SeoTitleCheck.vue resources/js/pages/SeoDescriptionCheck.vue tests/Feature/Tools/SeoTitleCheckControllerTest.php tests/Feature/Tools/SeoDescriptionCheckControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add title/description length check tools"
```

---

### Task 5: OGPチェックツール(`/ogp-check`)

**Files:**
- Create: `app/Http/Controllers/Tools/OgpCheckController.php`
- Create: `resources/js/pages/OgpCheck.vue`
- Test: `tests/Feature/Tools/OgpCheckControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`

**Interfaces:**
- Consumes: `App\Services\Tools\UrlFetchService::fetch(string $url): string`(Task 1)、`App\Services\Tools\UrlFetchException`(`reason`プロパティ、Task 1)、`App\Services\Tools\OgpCheckService::analyze(string $html): array{tags: array<string,string>, missing_recommended: list<string>}`(Task 2)。
- Produces: Controllerが返すJSON形状 `{ status: 'success', tags: Record<string,string>, missing_recommended: string[] } | { status: 'failed', reason: string, message: string }`。後続タスクはこれを使わない(独立ツール)。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\OgpCheckService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\UrlFetchException;
use App\Services\Tools\UrlFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OgpCheckController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('OgpCheck');
    }

    public function check(
        Request $request,
        UrlFetchService $urlFetchService,
        OgpCheckService $ogpCheckService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        try {
            $html = $urlFetchService->fetch($request->string('url')->toString());
        } catch (UrlFetchException $e) {
            return response()->json([
                'status' => 'failed',
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ], 422);
        }

        $analysis = $ogpCheckService->analyze($html);

        $eventLogger->log(
            toolSlug: 'ogp-check',
            eventType: 'process',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(array_merge(['status' => 'success'], $analysis));
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加:

```php
use App\Http\Controllers\Tools\OgpCheckController;
```

`seo-description-check`のルート群の直後に追加:

```php
Route::get('/ogp-check', [OgpCheckController::class, 'show'])->name('ogp-check.show');
Route::post('/ogp-check', [OgpCheckController::class, 'check'])
    ->middleware('throttle:10,1')
    ->name('ogp-check.check');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

```php
    [
        'slug' => 'ogp-check',
        'name' => 'OGPチェック',
        'category' => 'seo',
        'description' => '指定したURLのOGP・Twitter Cardタグを取得し、SNSシェアカードのプレビューを表示します。',
        'href' => '/ogp-check',
    ],
```

- [ ] **Step 4: 失敗するFeatureテストを書く**

```php
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
```

- [ ] **Step 5: テストが失敗することを確認**

Run: `php artisan test tests/Feature/Tools/OgpCheckControllerTest.php`
Expected: FAIL(ルート/Controllerが存在しない状態から順に解消していく。Step 1〜3の実装が終わっていればここはPASSに近いはずだが、フォームリクエストの前後関係を保つため本Stepの直前に一度確認する)

- [ ] **Step 6: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/OgpCheckControllerTest.php`
Expected: PASS(4テスト)

- [ ] **Step 7: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

interface OgpCheckSuccess {
    status: 'success';
    tags: Record<string, string>;
    missing_recommended: string[];
}

interface OgpCheckFailure {
    status: 'failed';
    reason: string;
    message: string;
}

const url = ref('');
const isChecking = ref(false);
const errorMessage = ref('');
const result = ref<OgpCheckSuccess | null>(null);

const canCheck = computed(() => url.value.trim() !== '' && !isChecking.value);

async function check() {
    if (!canCheck.value) {
        return;
    }

    isChecking.value = true;
    errorMessage.value = '';
    result.value = null;

    try {
        const response = await axios.post<OgpCheckSuccess | OgpCheckFailure>(
            '/ogp-check',
            { url: url.value },
        );
        result.value = response.data as OgpCheckSuccess;
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            errorMessage.value = error.response.data.message as string;
        } else {
            errorMessage.value = 'チェック中にエラーが発生しました。';
        }
    } finally {
        isChecking.value = false;
    }
}
</script>

<template>
    <Head title="OGPチェック - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    OGPチェック
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    URLを入力すると、OGP・Twitter
                    Cardタグを取得してSNSシェアカードのプレビューを確認できます。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <label for="url-input" class="block text-sm font-medium text-gray-700"
                >URL</label
            >
            <div class="mt-1 flex gap-2">
                <input
                    id="url-input"
                    v-model="url"
                    type="url"
                    placeholder="https://example.com/"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                />
                <button
                    type="button"
                    class="shrink-0 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="!canCheck"
                    @click="check"
                >
                    {{ isChecking ? 'チェック中...' : 'チェックする' }}
                </button>
            </div>

            <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                {{ errorMessage }}
            </p>

            <div v-if="result" class="mt-8 space-y-6">
                <div
                    v-if="result.missing_recommended.length > 0"
                    class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                >
                    以下の推奨タグが見つかりませんでした:
                    {{ result.missing_recommended.join(', ') }}
                </div>

                <div
                    v-if="result.tags['og:image']"
                    class="overflow-hidden rounded-md border border-gray-200"
                >
                    <img
                        :src="result.tags['og:image']"
                        alt=""
                        class="h-48 w-full object-cover"
                    />
                    <div class="p-4">
                        <p class="font-medium text-gray-900">
                            {{ result.tags['og:title'] }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ result.tags['og:description'] }}
                        </p>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <tbody>
                        <tr
                            v-for="(value, key) in result.tags"
                            :key="key"
                            class="border-b border-gray-100"
                        >
                            <td class="py-2 pr-4 font-mono text-xs text-gray-500">
                                {{ key }}
                            </td>
                            <td class="py-2 text-gray-800">{{ value }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 8: `composer test`とビルドを確認**

Run: `composer test`
Expected: 全件PASS

- [ ] **Step 9: コミット**

```bash
git add app/Http/Controllers/Tools/OgpCheckController.php resources/js/pages/OgpCheck.vue tests/Feature/Tools/OgpCheckControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add OGP check tool"
```

---

### Task 6: 構造化データ確認ツール(`/structured-data-check`)

**Files:**
- Create: `app/Http/Controllers/Tools/StructuredDataCheckController.php`
- Create: `resources/js/pages/StructuredDataCheck.vue`
- Test: `tests/Feature/Tools/StructuredDataCheckControllerTest.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`

**Interfaces:**
- Consumes: `App\Services\Tools\UrlFetchService::fetch(string $url): string`(Task 1)、`App\Services\Tools\UrlFetchException`(Task 1)、`App\Services\Tools\StructuredDataCheckService::analyze(string $html): list<array{raw:string,valid:bool,data?:mixed,error?:string,warnings:list<string>}>`(Task 3)。
- Produces: Controllerが返すJSON形状 `{ status: 'success', blocks: Array<{raw,valid,data?,error?,warnings}> } | { status: 'failed', reason: string, message: string }`。

- [ ] **Step 1: Controllerを実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\Tools\StructuredDataCheckService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\UrlFetchException;
use App\Services\Tools\UrlFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StructuredDataCheckController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('StructuredDataCheck');
    }

    public function check(
        Request $request,
        UrlFetchService $urlFetchService,
        StructuredDataCheckService $structuredDataCheckService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        try {
            $html = $urlFetchService->fetch($request->string('url')->toString());
        } catch (UrlFetchException $e) {
            return response()->json([
                'status' => 'failed',
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ], 422);
        }

        $blocks = $structuredDataCheckService->analyze($html);

        $eventLogger->log(
            toolSlug: 'structured-data-check',
            eventType: 'process',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['status' => 'success', 'blocks' => $blocks]);
    }
}
```

- [ ] **Step 2: ルートを追加**

`routes/web.php`の`use`宣言に追加:

```php
use App\Http\Controllers\Tools\StructuredDataCheckController;
```

`ogp-check`のルート群の直後に追加:

```php
Route::get('/structured-data-check', [StructuredDataCheckController::class, 'show'])->name('structured-data-check.show');
Route::post('/structured-data-check', [StructuredDataCheckController::class, 'check'])
    ->middleware('throttle:10,1')
    ->name('structured-data-check.check');
```

- [ ] **Step 3: `config/tools.php`にエントリを追加**

```php
    [
        'slug' => 'structured-data-check',
        'name' => '構造化データ確認',
        'category' => 'seo',
        'description' => '指定したURLのJSON-LD構造化データを取得・検証します。',
        'href' => '/structured-data-check',
    ],
```

- [ ] **Step 4: 失敗するFeatureテストを書く**

```php
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
});
```

- [ ] **Step 5: テストが通ることを確認**

Run: `php artisan test tests/Feature/Tools/StructuredDataCheckControllerTest.php`
Expected: PASS(3テスト)

- [ ] **Step 6: Inertiaページを作成**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

interface StructuredDataBlock {
    raw: string;
    valid: boolean;
    data?: unknown;
    error?: string;
    warnings: string[];
}

interface StructuredDataCheckSuccess {
    status: 'success';
    blocks: StructuredDataBlock[];
}

const url = ref('');
const isChecking = ref(false);
const errorMessage = ref('');
const result = ref<StructuredDataCheckSuccess | null>(null);

const canCheck = computed(() => url.value.trim() !== '' && !isChecking.value);

async function check() {
    if (!canCheck.value) {
        return;
    }

    isChecking.value = true;
    errorMessage.value = '';
    result.value = null;

    try {
        const response = await axios.post<StructuredDataCheckSuccess>(
            '/structured-data-check',
            { url: url.value },
        );
        result.value = response.data;
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            errorMessage.value = error.response.data.message as string;
        } else {
            errorMessage.value = 'チェック中にエラーが発生しました。';
        }
    } finally {
        isChecking.value = false;
    }
}
</script>

<template>
    <Head title="構造化データ確認 - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    構造化データ確認
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    URLを入力すると、ページ内のJSON-LD構造化データを取得・検証します。
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <label for="url-input" class="block text-sm font-medium text-gray-700"
                >URL</label
            >
            <div class="mt-1 flex gap-2">
                <input
                    id="url-input"
                    v-model="url"
                    type="url"
                    placeholder="https://example.com/"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                />
                <button
                    type="button"
                    class="shrink-0 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                    :disabled="!canCheck"
                    @click="check"
                >
                    {{ isChecking ? 'チェック中...' : 'チェックする' }}
                </button>
            </div>

            <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                {{ errorMessage }}
            </p>

            <p
                v-if="result && result.blocks.length === 0"
                class="mt-8 text-sm text-gray-500"
            >
                JSON-LD形式の構造化データは見つかりませんでした。
            </p>

            <div v-if="result" class="mt-8 space-y-4">
                <div
                    v-for="(block, index) in result.blocks"
                    :key="index"
                    class="rounded-md border border-gray-200 p-4"
                >
                    <p
                        class="text-sm font-medium"
                        :class="block.valid ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ block.valid ? '有効なJSON-LD' : `構文エラー: ${block.error}` }}
                    </p>
                    <ul
                        v-if="block.warnings.length > 0"
                        class="mt-2 list-inside list-disc text-sm text-amber-700"
                    >
                        <li v-for="warning in block.warnings" :key="warning">
                            {{ warning }}
                        </li>
                    </ul>
                    <pre
                        class="mt-3 overflow-x-auto rounded bg-gray-50 p-3 font-mono text-xs text-gray-800"
                        >{{ block.raw }}</pre
                    >
                </div>
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 7: `composer test`を確認**

Run: `composer test`
Expected: 全件PASS

- [ ] **Step 8: コミット**

```bash
git add app/Http/Controllers/Tools/StructuredDataCheckController.php resources/js/pages/StructuredDataCheck.vue tests/Feature/Tools/StructuredDataCheckControllerTest.php routes/web.php config/tools.php
git commit -m "feat: add structured data check tool"
```

---

### Task 7: 動作確認・ドキュメント更新

**Files:**
- Modify: `tests/Feature/Admin/DashboardControllerTest.php:46`(`->has('stats', 17)` → `->has('stats', 21)`)
- Modify: `TASKS.md`(Phase 9セクション追加・チェック)
- Modify: `SPEC.md`(§20スコープ外からSEOカテゴリの完了扱いへの反映は既にPhase開始前に完了しているため、実装順序の記述のみ確認)

**Interfaces:**
- Consumes: すべてのTask 1〜6の成果物。
- Produces: なし(検証タスク)。

- [ ] **Step 1: Admin集計テストのツール数を更新**

`tests/Feature/Admin/DashboardControllerTest.php:46`の`->has('stats', 17)`を`->has('stats', 21)`に変更する(4ツール追加のため17→21)。

- [ ] **Step 2: バックエンド・フロントの全チェックを実行**

Run: `composer ci:check`
Expected: `npm run lint:check` / `format:check` / `types:check` / `npm run test` / `composer test`(Pint/PHPStan/Pest)がすべてグリーン

- [ ] **Step 3: `npm run build`を実行**

Run: `npm run build`
Expected: ビルドエラーなし

- [ ] **Step 4: `TASKS.md`にPhase 9セクションを追記**

Phase 8セクションの直後、「## 今後の実装順序」の直前に以下を追記する形式で、実装した内容(UrlFetchServiceの新設・SSRF対策・4ツール)をチェック済みとして記述し、「## 今後の実装順序」の行を「画像の切り抜き」のみに更新する。

- [ ] **Step 5: Playwrightで4ツールすべての実ブラウザ動作確認**

`php artisan serve`と`npx vite`をそれぞれ別ポートで起動し、以下を確認する:
- title/description文字数チェック: 文字数・バイト数が正しく表示され、推奨レンジ超過時に警告色に変わること
- OGPチェック: 実際に到達可能な公開URL(例: 自ドメインのトップページ)でタグが取得できること、無効なURL・プライベートIPを指すホスト名で422エラーメッセージが表示されること
- 構造化データ確認: JSON-LDを含む公開URLで構造化データがツリー表示されること、含まれないページで「見つかりませんでした」が表示されること

- [ ] **Step 6: コミット**

```bash
git add tests/Feature/Admin/DashboardControllerTest.php TASKS.md
git commit -m "test: update tool count and mark Phase 9 complete in TASKS.md"
```

---

## Self-Review 結果

- **Spec coverage**: SPEC.md §23.1(Task 4)、§23.2(Task 4)、§23.3(Task 1)、§23.4(Task 5)、§23.5(Task 3・Task 6)をすべてカバー。
- **Placeholder scan**: 「TBD」等の記述なし。全ステップに具体的なコード・コマンドを記載済み。
- **Type consistency**: `UrlFetchService::fetch(string $url): string`と`UrlFetchException::$reason`はTask 1で定義し、Task 5・6で同じシグネチャを使用。`OgpCheckService::analyze()`(Task 2)・`StructuredDataCheckService::analyze()`(Task 3)の戻り値型もTask 5・6のController実装と一致。
