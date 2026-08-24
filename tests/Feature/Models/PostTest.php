<?php

use App\Models\Post;

test('the published scope only includes posts with a past published_at', function () {
    $published = Post::create([
        'title' => '公開済み記事',
        'slug' => 'published-post',
        'type' => 'announcement',
        'body_markdown' => '本文',
        'published_at' => now()->subDay(),
    ]);

    Post::create([
        'title' => '下書き記事',
        'slug' => 'draft-post',
        'type' => 'announcement',
        'body_markdown' => '本文',
        'published_at' => null,
    ]);

    Post::create([
        'title' => '予約投稿',
        'slug' => 'scheduled-post',
        'type' => 'announcement',
        'body_markdown' => '本文',
        'published_at' => now()->addDay(),
    ]);

    $results = Post::query()->published()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($published->id);
});
