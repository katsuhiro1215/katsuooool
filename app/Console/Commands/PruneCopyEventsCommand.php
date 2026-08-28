<?php

namespace App\Console\Commands;

use App\Models\CopyEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneCopyEventsCommand extends Command
{
    protected $signature = 'copy-events:prune {--days=7 : Delete events older than this many days}';

    protected $description = 'Delete copy_events records older than the retention period';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = CopyEvent::where('created_at', '<', Carbon::now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} copy event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
