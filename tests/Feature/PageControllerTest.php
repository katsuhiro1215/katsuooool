<?php

test('the about page renders', function () {
    $response = $this->get(route('page.about'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('About'));
});

test('the service page renders', function () {
    $response = $this->get(route('page.service'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Service'));
});

test('the company page renders with company info from config', function () {
    config(['company' => [
        'name' => 'テスト株式会社',
        'representative' => '山田太郎',
        'address' => '東京都渋谷区1-1-1',
        'contact' => 'contact@example.com',
    ]]);

    $response = $this->get(route('page.company'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Company')
        ->where('company.name', 'テスト株式会社')
        ->where('company.representative', '山田太郎')
        ->where('company.address', '東京都渋谷区1-1-1')
        ->where('company.contact', 'contact@example.com'));
});
