<?php

use App\Models\Post;

test('unauthenticated visitors are redirected to the admin login page', function () {
    $response = $this->get(route('admin.posts.index'));

    $response->assertRedirect(route('admin.login'));
});

test('the post list shows all posts including drafts, newest published first', function () {
    actingAsAdmin();

    $published = Post::create([
        'title' => '公開済み',
        'slug' => 'published',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now()->subDay(),
    ]);

    $draft = Post::create([
        'title' => '下書き',
        'slug' => 'draft',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->get(route('admin.posts.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/posts/Index')
        ->has('posts.data', 2)
    );
});

test('an admin can create a new post', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.posts.store'), [
        'title' => '新しいお知らせ',
        'slug' => 'new-announcement',
        'type' => 'announcement',
        'body_markdown' => '本文です。',
        'published_at' => null,
    ]);

    $response->assertRedirect(route('admin.posts.index'));
    $this->assertDatabaseHas('posts', [
        'slug' => 'new-announcement',
        'title' => '新しいお知らせ',
    ]);
});

test('creating a post requires a valid type', function () {
    actingAsAdmin();

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.posts.store'), [
            'title' => 'タイトル',
            'slug' => 'invalid-type-post',
            'type' => 'invalid',
            'body_markdown' => '本文',
            'published_at' => null,
        ]);

    $response->assertStatus(422);
});

test('creating a post requires a unique slug', function () {
    actingAsAdmin();

    Post::create([
        'title' => '既存記事',
        'slug' => 'existing-slug',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now(),
    ]);

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.posts.store'), [
            'title' => '新規記事',
            'slug' => 'existing-slug',
            'type' => 'update',
            'body_markdown' => '本文',
            'published_at' => null,
        ]);

    $response->assertStatus(422);
});
