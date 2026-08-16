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
