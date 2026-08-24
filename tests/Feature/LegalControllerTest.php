<?php

test('the tokushoho page renders', function () {
    $response = $this->get(route('legal.tokushoho'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('legal/Tokushoho'));
});

test('the privacy policy page renders', function () {
    $response = $this->get(route('legal.privacy-policy'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('legal/PrivacyPolicy'));
});

test('the terms page renders', function () {
    $response = $this->get(route('legal.terms'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('legal/Terms'));
});

test('the cookie policy page renders', function () {
    $response = $this->get(route('legal.cookie-policy'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('legal/CookiePolicy'));
});
