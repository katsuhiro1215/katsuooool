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
