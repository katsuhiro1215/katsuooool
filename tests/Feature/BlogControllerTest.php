<?php

use App\Models\Post;

test('the index page lists only published posts newest first', function () {
    $older = Post::create([
        'title' => '古いお知らせ',
        'slug' => 'old-announcement',
        'type' => 'announcement',
        'body_markdown' => '本文',
        'published_at' => now()->subDays(5),
    ]);

    $newer = Post::create([
        'title' => '新しいお知らせ',
        'slug' => 'new-announcement',
        'type' => 'update',
        'body_markdown' => '本文',
        'published_at' => now()->subDay(),
    ]);

    Post::create([
        'title' => '下書き',
        'slug' => 'draft',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->get(route('blog.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Blog/Index')
        ->has('posts.data', 2)
        ->where('posts.data.0.slug', $newer->slug)
        ->where('posts.data.1.slug', $older->slug)
    );
});

test('the show page renders a published post as html', function () {
    Post::create([
        'title' => 'v2.0リリース',
        'slug' => 'v2-0-release',
        'type' => 'update',
        'body_markdown' => "# v2.0\n\n新機能を追加しました。",
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get(route('blog.show', 'v2-0-release'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Blog/Show')
        ->where('post.title', 'v2.0リリース')
        ->has('post.body_html')
    );
});

test('a draft post returns 404 on the show page', function () {
    Post::create([
        'title' => '下書き',
        'slug' => 'draft-post',
        'type' => 'fix',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    $response = $this->get(route('blog.show', 'draft-post'));

    $response->assertNotFound();
});
