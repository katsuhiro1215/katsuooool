<?php

namespace App\Console\Commands;

use App\Models\ToolEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneToolEventsCommand extends Command
{
    protected $signature = 'tool-events:prune {--days=30 : Delete events older than this many days}';

    protected $description = 'Delete tool_events records older than the retention period';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = ToolEvent::where('created_at', '<', Carbon::now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} tool event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
