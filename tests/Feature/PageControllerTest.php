<?php

test('the about page renders', function () {
    $response = $this->get(route('page.about'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('About'));
});
