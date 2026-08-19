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

                if ($location === '') {
                    throw UrlFetchException::httpError($response->status());
                }

                $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);

                continue;
            }

            if (! $response->successful()) {
                throw UrlFetchException::httpError($response->status());
            }

            $contentType = strtolower($response->header('Content-Type'));

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

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            throw UrlFetchException::invalidUrl();
        }

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

        if ($base === false || ! isset($base['scheme'], $base['host'])) {
            throw UrlFetchException::invalidUrl();
        }

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
