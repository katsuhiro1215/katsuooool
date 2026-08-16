<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('tool-tmp:prune deletes batch directories older than the retention window', function () {
    $disk = Storage::disk('local');

    $disk->put('tool-tmp/old-batch/a.jpg', 'fake-old');
    $disk->put('tool-tmp/fresh-batch/b.jpg', 'fake-fresh');

    touch($disk->path('tool-tmp/old-batch/a.jpg'), now()->subHours(25)->getTimestamp());
    touch($disk->path('tool-tmp/fresh-batch/b.jpg'), now()->subHours(1)->getTimestamp());

    $this->artisan('tool-tmp:prune')->assertSuccessful();

    expect($disk->exists('tool-tmp/old-batch/a.jpg'))->toBeFalse();
    expect($disk->exists('tool-tmp/fresh-batch/b.jpg'))->toBeTrue();
});
