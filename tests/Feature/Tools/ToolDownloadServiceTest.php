<?php

use App\Services\Tools\ToolDownloadService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('zip bundles multiple files into a single archive on the local disk', function () {
    Storage::disk('local')->put('tool-tmp/batch-1/a.jpg', 'fake-image-a');
    Storage::disk('local')->put('tool-tmp/batch-1/b.jpg', 'fake-image-b');

    $service = new ToolDownloadService;

    $zipPath = $service->zip('batch-1', [
        ['path' => 'tool-tmp/batch-1/a.jpg', 'download_name' => 'photo-a.jpg'],
        ['path' => 'tool-tmp/batch-1/b.jpg', 'download_name' => 'photo-b.jpg'],
    ], 'compressed-images.zip');

    Storage::disk('local')->assertExists($zipPath);
    expect($zipPath)->toEndWith('compressed-images.zip');
});

test('signedUrl generates a temporary signed URL for the download route', function () {
    Storage::disk('local')->put('tool-tmp/batch-1/a.jpg', 'fake-image-a');

    $service = new ToolDownloadService;

    $url = $service->signedUrl('tool-tmp/batch-1/a.jpg', 'photo-a.jpg');

    expect($url)->toContain('/tool-download/');
    expect($url)->toContain('signature=');
});
