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

test('the edit page shows the existing snippet data', function () {
    actingAsAdmin();

    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '編集対象',
        'html' => '<a>edit</a>',
        'css' => 'a {}',
    ]);

    $response = $this->get(route('admin.ui-pattern-snippets.edit', $snippet));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/ui-pattern-snippets/Edit')
        ->where('snippet.title', '編集対象')
    );
});

test('an admin can update a snippet', function () {
    actingAsAdmin();

    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '更新前',
        'html' => '<a>before</a>',
        'css' => 'a {}',
    ]);

    $response = $this->put(route('admin.ui-pattern-snippets.update', $snippet), [
        'category' => 'button',
        'title' => '更新後',
        'html' => '<a>after</a>',
        'css' => 'a { color: red; }',
    ]);

    $response->assertRedirect(route('admin.ui-pattern-snippets.index'));
    $this->assertDatabaseHas('ui_pattern_snippets', [
        'id' => $snippet->id,
        'title' => '更新後',
    ]);
});

test('an admin can delete a snippet', function () {
    actingAsAdmin();

    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => '削除対象',
        'html' => '<a>delete</a>',
        'css' => 'a {}',
    ]);

    $response = $this->delete(route('admin.ui-pattern-snippets.destroy', $snippet));

    $response->assertRedirect(route('admin.ui-pattern-snippets.index'));
    $this->assertDatabaseMissing('ui_pattern_snippets', ['id' => $snippet->id]);
});
