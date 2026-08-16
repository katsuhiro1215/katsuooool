<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneToolTempFilesCommand extends Command
{
    protected $signature = 'tool-tmp:prune {--hours=24 : Delete batch directories whose newest file is older than this many hours}';

    protected $description = 'Delete tool-tmp upload/output directories older than the retention window';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $disk = Storage::disk('local');
        $cutoff = now()->subHours($hours)->getTimestamp();

        $deletedCount = 0;

        foreach ($disk->directories('tool-tmp') as $batchDirectory) {
            $newestModifiedAt = collect($disk->allFiles($batchDirectory))
                ->map(fn (string $file) => $disk->lastModified($file))
                ->max();

            $newestModifiedAt ??= 0;

            if ($newestModifiedAt < $cutoff) {
                $disk->deleteDirectory($batchDirectory);
                $deletedCount++;
            }
        }

        $this->info("Deleted {$deletedCount} tool-tmp batch director(y/ies) older than {$hours} hours.");

        return self::SUCCESS;
    }
}
