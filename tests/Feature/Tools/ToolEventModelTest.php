<?php

use App\Models\ToolEvent;

test('a tool event can be created and does not track updated_at', function () {
    $event = ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'upload',
        'file_count' => 3,
        'input_bytes' => 1_000_000,
        'output_bytes' => null,
        'ip_hash' => hash('sha256', 'test'),
        'user_agent' => 'PestTest/1.0',
    ]);

    expect($event->exists)->toBeTrue();
    expect($event->created_at)->not->toBeNull();
    expect(ToolEvent::UPDATED_AT)->toBeNull();
});
