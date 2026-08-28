<?php

namespace App\Services;

use App\Models\CopyEvent;
use Illuminate\Database\Eloquent\Model;

class CopyPopularityTracker
{
    public function record(Model $copyable, ?string $ip): void
    {
        $ipHash = $this->hashIp($ip);

        $event = CopyEvent::query()->createOrFirst([
            'copyable_type' => $copyable::class,
            'copyable_id' => $copyable->getKey(),
            'ip_hash' => $ipHash,
        ]);

        if ($event->wasRecentlyCreated) {
            $copyable->increment('copy_count');
        }
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.now()->toDateString().config('app.key'));
    }
}
