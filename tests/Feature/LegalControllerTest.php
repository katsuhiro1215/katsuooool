<?php

test('the tokushoho page renders', function () {
    $response = $this->get(route('legal.tokushoho'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('legal/Tokushoho'));
});
