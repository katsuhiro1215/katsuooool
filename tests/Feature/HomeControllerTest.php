<?php

test('the home page renders with the tool catalog', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Home')
        ->has('tools')
        ->where('tools.0.slug', 'image-compress')
    );
});

test('toolNav is shared with navigation fields only', function () {
    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('toolNav', count(config('tools')))
        ->where('toolNav.0.slug', 'image-compress')
        ->where('toolNav.0.href', '/image-compress')
        ->missing('toolNav.0.description')
    );
});

test('toolNav is shared on non-tool public pages too', function () {
    $this->get('/faq')->assertInertia(fn ($page) => $page
        ->has('toolNav')
    );
});
