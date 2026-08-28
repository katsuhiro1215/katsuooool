<?php

use App\Services\Tools\WebsiteStarterService;

test('buildTitle formats prefecture, industry label, and site name', function () {
    $service = new WebsiteStarterService;

    $title = $service->buildTitle('株式会社カツコード', '美容・サロン', '奈良県');

    expect($title)->toBe('奈良県の美容・サロン｜株式会社カツコード');
});

test('buildMetaTagsBlock includes description and OGP tags with escaped values', function () {
    $service = new WebsiteStarterService;

    $block = $service->buildMetaTagsBlock(
        title: '奈良県の美容・サロン｜"かつ" & Co.',
        description: '奈良市の美容室です。',
        siteUrl: 'https://example.com',
    );

    expect($block)
        ->toContain('<meta name="description" content="奈良市の美容室です。">')
        ->toContain('<meta property="og:title" content="奈良県の美容・サロン｜&quot;かつ&quot; &amp; Co.">')
        ->toContain('<meta property="og:description" content="奈良市の美容室です。">')
        ->toContain('<meta property="og:type" content="website">')
        ->toContain('<meta property="og:url" content="https://example.com">')
        ->toContain('og:image');
});

test('buildHtmlHeadBlock includes title, canonical, favicon links, and the meta tags block', function () {
    $service = new WebsiteStarterService;

    $block = $service->buildHtmlHeadBlock(
        title: '奈良県の美容・サロン｜株式会社カツコード',
        description: '奈良市の美容室です。',
        siteUrl: 'https://example.com',
    );

    expect($block)
        ->toContain('<title>奈良県の美容・サロン｜株式会社カツコード</title>')
        ->toContain('<link rel="canonical" href="https://example.com">')
        ->toContain('<link rel="icon" href="/favicon.ico">')
        ->toContain('<link rel="apple-touch-icon" href="/apple-touch-icon.png">')
        ->toContain('<meta name="description" content="奈良市の美容室です。">')
        ->toContain('<meta property="og:type" content="website">');
});
