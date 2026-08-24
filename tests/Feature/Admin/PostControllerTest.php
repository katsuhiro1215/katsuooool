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

test('draft posts appear before published posts in the admin list', function () {
    actingAsAdmin();

    Post::create([
        'title' => '公開済み',
        'slug' => 'published-old',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now()->subDays(10),
    ]);

    $draft = Post::create([
        'title' => '下書き',
        'slug' => 'a-draft',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->get(route('admin.posts.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('admin/posts/Index')
        ->where('posts.data.0.slug', $draft->slug)
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

test('the edit page shows the existing post data', function () {
    actingAsAdmin();

    $post = Post::create([
        'title' => '編集対象',
        'slug' => 'editable-post',
        'type' => 'fix',
        'body_markdown' => '既存の本文',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get(route('admin.posts.edit', $post));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/posts/Edit')
        ->where('post.title', '編集対象')
        ->where('post.slug', 'editable-post')
    );
});

test('an admin can update a post', function () {
    actingAsAdmin();

    $post = Post::create([
        'title' => '更新前',
        'slug' => 'update-target',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->put(route('admin.posts.update', $post), [
        'title' => '更新後',
        'slug' => 'update-target',
        'type' => 'update',
        'body_markdown' => '更新後の本文',
        'published_at' => null,
    ]);

    $response->assertRedirect(route('admin.posts.index'));
    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'title' => '更新後',
        'type' => 'update',
    ]);
});

test('updating a post can keep its own slug without triggering a uniqueness error', function () {
    actingAsAdmin();

    $post = Post::create([
        'title' => 'タイトル',
        'slug' => 'same-slug',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now(),
    ]);

    $response = $this->put(route('admin.posts.update', $post), [
        'title' => 'タイトル(更新)',
        'slug' => 'same-slug',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response->assertRedirect(route('admin.posts.index'));
});

test('an admin can delete a post', function () {
    actingAsAdmin();

    $post = Post::create([
        'title' => '削除対象',
        'slug' => 'to-be-deleted',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->delete(route('admin.posts.destroy', $post));

    $response->assertRedirect(route('admin.posts.index'));
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});
