<?php

test('the faq page renders', function () {
    $response = $this->get(route('faq.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Faq'));
});
