<?php

use App\Models\UiPatternSnippet;

test('unauthenticated visitors are redirected to the admin login page', function () {
    $response = $this->get(route('admin.ui-pattern-snippets.index'));

    $response->assertRedirect(route('admin.login'));
});

test('the snippet list shows all snippets', function () {
    actingAsAdmin();

    UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'サンプルボタン',
        'html' => '<a>button</a>',
        'css' => 'a {}',
    ]);

    $response = $this->get(route('admin.ui-pattern-snippets.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/ui-pattern-snippets/Index')
        ->has('snippets.data', 1)
    );
});

test('an admin can create a new snippet', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.ui-pattern-snippets.store'), [
        'category' => 'button',
        'title' => '斜線シャドウボタン',
        'html' => '<a href="#"><span>Button</span></a>',
        'css' => 'a { display: block; }',
    ]);

    $response->assertRedirect(route('admin.ui-pattern-snippets.index'));
    $this->assertDatabaseHas('ui_pattern_snippets', [
        'title' => '斜線シャドウボタン',
    ]);
});

test('creating a snippet requires a valid category', function () {
    actingAsAdmin();

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.ui-pattern-snippets.store'), [
            'category' => 'invalid',
            'title' => 'タイトル',
            'html' => '<a>a</a>',
            'css' => 'a {}',
        ]);

    $response->assertStatus(422);
});
