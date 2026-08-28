<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ColorExample;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ColorExampleController extends Controller
{
    public function index(): Response
    {
        $examples = ColorExample::query()
            ->orderBy('type')
            ->orderBy('id')
            ->paginate(20);

        return Inertia::render('admin/color-examples/Index', [
            'examples' => $examples,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/color-examples/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        ColorExample::create($this->validated($request));

        return Redirect::route('admin.color-examples.index');
    }

    public function edit(ColorExample $colorExample): Response
    {
        return Inertia::render('admin/color-examples/Edit', [
            'example' => [
                'id' => $colorExample->id,
                'type' => $colorExample->type,
                'title' => $colorExample->title,
                'colors' => $colorExample->colors,
                'angle' => $colorExample->angle,
            ],
        ]);
    }

    public function update(Request $request, ColorExample $colorExample): RedirectResponse
    {
        $colorExample->update($this->validated($request));

        return Redirect::route('admin.color-examples.index');
    }

    public function destroy(ColorExample $colorExample): RedirectResponse
    {
        $colorExample->delete();

        return Redirect::route('admin.color-examples.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $type = $request->string('type')->toString();

        return $request->validate([
            'type' => ['required', 'string', Rule::in(ColorExample::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'colors' => [
                'required',
                'array',
                $type === 'gradient' ? 'size:2' : 'min:2',
                $type === 'palette' ? 'max:6' : ($type === 'scheme' ? 'max:4' : 'max:2'),
            ],
            'colors.*' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'angle' => [$type === 'gradient' ? 'required' : 'nullable', 'integer', 'min:0', 'max:360'],
        ]);
    }
}
