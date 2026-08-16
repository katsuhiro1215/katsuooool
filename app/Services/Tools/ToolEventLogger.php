<?php

namespace App\Services\Tools;

use App\Models\ToolEvent;

class ToolEventLogger
{
    /**
     * @param  array{file_count?: int, input_bytes?: int, output_bytes?: int}  $data
     */
    public function log(
        string $toolSlug,
        string $eventType,
        array $data = [],
        ?string $ip = null,
        ?string $userAgent = null,
    ): ToolEvent {
        return ToolEvent::create([
            'tool_slug' => $toolSlug,
            'event_type' => $eventType,
            'file_count' => $data['file_count'] ?? null,
            'input_bytes' => $data['input_bytes'] ?? null,
            'output_bytes' => $data['output_bytes'] ?? null,
            'ip_hash' => $this->hashIp($ip),
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.now()->toDateString().config('app.key'));
    }
}
