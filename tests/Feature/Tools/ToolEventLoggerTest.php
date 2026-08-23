<?php

use App\Models\User;
use App\Services\Tools\ToolEventLogger;

test('log records an event without storing the raw ip address', function () {
    $logger = new ToolEventLogger;

    $event = $logger->log(
        toolSlug: 'image-compress',
        eventType: 'upload',
        data: ['file_count' => 2, 'input_bytes' => 500_000],
        ip: '203.0.113.42',
        userAgent: 'PestTest/1.0',
    );

    expect($event->tool_slug)->toBe('image-compress');
    expect($event->event_type)->toBe('upload');
    expect($event->file_count)->toBe(2);
    expect($event->input_bytes)->toBe(500_000);
    expect($event->ip_hash)->not->toBeNull();
    expect($event->ip_hash)->not->toContain('203.0.113.42');
    expect(strlen($event->ip_hash))->toBe(64);
});

test('log produces the same hash for the same ip on the same day', function () {
    $logger = new ToolEventLogger;

    $first = $logger->log('image-compress', 'upload', ip: '203.0.113.42');
    $second = $logger->log('image-compress', 'upload', ip: '203.0.113.42');

    expect($first->ip_hash)->toBe($second->ip_hash);
});

test('log handles a null ip gracefully', function () {
    $logger = new ToolEventLogger;

    $event = $logger->log('image-compress', 'upload', ip: null);

    expect($event->ip_hash)->toBeNull();
});

test('log records the user id when provided and leaves it null otherwise', function () {
    $logger = new ToolEventLogger;
    $user = User::factory()->create();

    $withUser = $logger->log('image-compress', 'upload', userId: $user->id);
    $withoutUser = $logger->log('image-compress', 'upload');

    expect($withUser->user_id)->toBe($user->id);
    expect($withoutUser->user_id)->toBeNull();
});
