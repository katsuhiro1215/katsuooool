<?php

namespace App\Services\Tools;

class WebsiteStarterService
{
    public function buildTitle(string $siteName, string $industryLabel, string $prefecture): string
    {
        return "{$prefecture}の{$industryLabel}｜{$siteName}";
    }

    public function buildMetaTagsBlock(string $title, string $description, string $siteUrl): string
    {
        $escapedTitle = $this->escape($title);
        $escapedDescription = $this->escape($description);
        $escapedUrl = $this->escape($siteUrl);

        return implode("\n", [
            '<meta name="description" content="'.$escapedDescription.'">',
            '<meta property="og:title" content="'.$escapedTitle.'">',
            '<meta property="og:description" content="'.$escapedDescription.'">',
            '<meta property="og:type" content="website">',
            '<meta property="og:url" content="'.$escapedUrl.'">',
            '<!-- ダウンロードしたOGP画像を配置したURLに置き換えてください -->',
            '<meta property="og:image" content="">',
        ]);
    }

    public function buildHtmlHeadBlock(string $title, string $description, string $siteUrl): string
    {
        $escapedTitle = $this->escape($title);
        $escapedUrl = $this->escape($siteUrl);

        return implode("\n", [
            '<title>'.$escapedTitle.'</title>',
            '<link rel="canonical" href="'.$escapedUrl.'">',
            '<!-- ダウンロードしたfaviconファイルを配置したパスに置き換えてください -->',
            '<link rel="icon" href="/favicon.ico">',
            '<link rel="apple-touch-icon" href="/apple-touch-icon.png">',
            $this->buildMetaTagsBlock($title, $description, $siteUrl),
        ]);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
