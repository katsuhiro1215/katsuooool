<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ToolEvent;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = collect(config('tools'))
            ->map(function (array $tool) {
                // A closure that returns a fresh Builder instance each call
                // (Eloquent's ::query() always builds a new one), so no
                // clone() is needed between the independent aggregate calls
                // below.
                $baseQuery = fn () => ToolEvent::query()
                    ->where('tool_slug', $tool['slug'])
                    ->where('event_type', 'process');

                return [
                    'slug' => $tool['slug'],
                    'name' => $tool['name'],
                    'total_executions' => $baseQuery()->count(),
                    'total_files' => (int) $baseQuery()->sum('file_count'),
                    'last_7_days' => $baseQuery()->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
                    'last_30_days' => $baseQuery()->where('created_at', '>=', Carbon::now()->subDays(30))->count(),
                ];
            })
            ->values();

        return Inertia::render('admin/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
