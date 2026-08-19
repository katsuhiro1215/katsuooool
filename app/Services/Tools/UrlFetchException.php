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
