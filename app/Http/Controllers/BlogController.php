<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\MarkdownRenderer;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(MarkdownRenderer $markdownRenderer): Response
    {
        $posts = Post::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate(10)
            ->through(fn (Post $post) => [
                'title' => $post->title,
                'slug' => $post->slug,
                'type' => $post->type,
                'published_at' => $post->published_at?->toIso8601String(),
                'excerpt' => Str::limit(strip_tags($markdownRenderer->toHtml($post->body_markdown)), 100),
            ]);

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug, MarkdownRenderer $markdownRenderer): Response
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Blog/Show', [
            'post' => [
                'title' => $post->title,
                'type' => $post->type,
                'published_at' => $post->published_at?->toIso8601String(),
                'body_html' => $markdownRenderer->toHtml($post->body_markdown),
            ],
        ]);
    }
}
