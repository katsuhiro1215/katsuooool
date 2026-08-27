<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UiPatternSnippet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UiPatternSnippetController extends Controller
{
    public function index(): Response
    {
        $snippets = UiPatternSnippet::query()
            ->orderBy('category')
            ->orderBy('id')
            ->paginate(20);

        return Inertia::render('admin/ui-pattern-snippets/Index', [
            'snippets' => $snippets,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/ui-pattern-snippets/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        UiPatternSnippet::create($this->validated($request));

        return Redirect::route('admin.ui-pattern-snippets.index');
    }

    public function edit(UiPatternSnippet $snippet): Response
    {
        return Inertia::render('admin/ui-pattern-snippets/Edit', [
            'snippet' => [
                'id' => $snippet->id,
                'category' => $snippet->category,
                'title' => $snippet->title,
                'html' => $snippet->html,
                'css' => $snippet->css,
                'is_premium' => $snippet->is_premium,
            ],
        ]);
    }

    public function update(Request $request, UiPatternSnippet $snippet): RedirectResponse
    {
        $snippet->update($this->validated($request));

        return Redirect::route('admin.ui-pattern-snippets.index');
    }

    public function destroy(UiPatternSnippet $snippet): RedirectResponse
    {
        $snippet->delete();

        return Redirect::route('admin.ui-pattern-snippets.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', Rule::in(UiPatternSnippet::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'html' => ['required', 'string'],
            'css' => ['required', 'string'],
            'is_premium' => ['boolean'],
        ]);
    }
}
