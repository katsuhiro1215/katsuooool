<?php

beforeEach(function () {
    config(['company' => [
        'name' => 'テスト株式会社',
        'representative' => '山田太郎',
        'address' => '東京都渋谷区1-1-1',
        'contact' => 'contact@example.com',
    ]]);
});

test('the tokushoho page renders with company info from config', function () {
    $response = $this->get(route('legal.tokushoho'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('legal/Tokushoho')
        ->where('company.name', 'テスト株式会社')
        ->where('company.representative', '山田太郎')
        ->where('company.address', '東京都渋谷区1-1-1')
        ->where('company.contact', 'contact@example.com'));
});

test('the privacy policy page renders with company info from config', function () {
    $response = $this->get(route('legal.privacy-policy'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('legal/PrivacyPolicy')
        ->where('company.name', 'テスト株式会社')
        ->where('company.contact', 'contact@example.com'));
});

test('the terms page renders with company info from config', function () {
    $response = $this->get(route('legal.terms'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('legal/Terms')
        ->where('company.name', 'テスト株式会社'));
});

test('the cookie policy page renders', function () {
    $response = $this->get(route('legal.cookie-policy'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('legal/CookiePolicy'));
});
