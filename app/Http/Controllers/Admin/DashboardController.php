<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ToolEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        /** @var list<array{slug: string, name: string, category: string, description: string, href: string}> $tools */
        $tools = Config::array('tools');

        $sevenDaysAgo = Carbon::now()->subDays(7);
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $rowsBySlug = ToolEvent::query()
            ->whereIn('event_type', ['process', 'failed'])
            ->selectRaw(
                'tool_slug, '.
                "SUM(CASE WHEN event_type = 'process' THEN 1 ELSE 0 END) as total_executions, ".
                "SUM(CASE WHEN event_type = 'process' THEN file_count ELSE 0 END) as total_files, ".
                "SUM(CASE WHEN event_type = 'process' AND created_at >= ? THEN 1 ELSE 0 END) as last_7_days, ".
                "SUM(CASE WHEN event_type = 'process' AND created_at >= ? THEN 1 ELSE 0 END) as last_30_days, ".
                "SUM(CASE WHEN event_type = 'failed' THEN 1 ELSE 0 END) as error_count",
                [$sevenDaysAgo, $thirtyDaysAgo],
            )
            ->groupBy('tool_slug')
            ->get()
            ->keyBy('tool_slug');

        $stats = collect($tools)
            ->map(function (array $tool) use ($rowsBySlug) {
                $row = $rowsBySlug->get($tool['slug']);

                return [
                    'slug' => $tool['slug'],
                    'name' => $tool['name'],
                    'category' => $tool['category'],
                    'total_executions' => $row ? (int) $row->getAttribute('total_executions') : 0,
                    'total_files' => $row ? (int) $row->getAttribute('total_files') : 0,
                    'last_7_days' => $row ? (int) $row->getAttribute('last_7_days') : 0,
                    'last_30_days' => $row ? (int) $row->getAttribute('last_30_days') : 0,
                    'error_count' => $row ? (int) $row->getAttribute('error_count') : 0,
                ];
            })
            ->values();

        $dailyRowsByKey = ToolEvent::query()
            ->whereIn('event_type', ['process', 'failed'])
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw(
                'tool_slug, DATE(created_at) as date, '.
                "SUM(CASE WHEN event_type = 'process' THEN 1 ELSE 0 END) as executions, ".
                "SUM(CASE WHEN event_type = 'failed' THEN 1 ELSE 0 END) as errors",
            )
            ->groupByRaw('tool_slug, DATE(created_at)')
            ->get()
            ->keyBy(fn (ToolEvent $row) => $row->tool_slug.'|'.$row->getAttribute('date'));

        $dateRange = collect(range(29, 0))
            ->map(fn (int $daysAgo) => Carbon::now()->subDays($daysAgo)->toDateString());

        $dailyStats = collect($tools)
            ->mapWithKeys(function (array $tool) use ($dateRange, $dailyRowsByKey) {
                $series = $dateRange
                    ->map(function (string $date) use ($tool, $dailyRowsByKey) {
                        $row = $dailyRowsByKey->get($tool['slug'].'|'.$date);

                        return [
                            'date' => $date,
                            'executions' => $row ? (int) $row->getAttribute('executions') : 0,
                            'errors' => $row ? (int) $row->getAttribute('errors') : 0,
                        ];
                    })
                    ->values();

                return [$tool['slug'] => $series];
            });

        return Inertia::render('admin/Dashboard', [
            'stats' => $stats,
            'dailyStats' => $dailyStats,
        ]);
    }
}
