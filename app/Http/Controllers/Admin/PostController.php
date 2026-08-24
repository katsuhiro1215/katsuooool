<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(): Response
    {
        $posts = Post::query()
            ->orderByDesc('published_at')
            ->paginate(20);

        return Inertia::render('admin/posts/Index', [
            'posts' => $posts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/posts/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        Post::create($this->validated($request));

        return Redirect::route('admin.posts.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('posts', 'slug')->ignore($post),
            ],
            'type' => ['required', 'string', Rule::in(Post::TYPES)],
            'body_markdown' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
