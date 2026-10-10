<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'toolNav' => fn () => array_map(
                fn (array $tool) => [
                    'slug' => $tool['slug'],
                    'name' => $tool['name'],
                    'category' => $tool['category'],
                    'href' => $tool['href'],
                ],
                array_values(Config::array('tools')),
            ),
        ];
    }
}
