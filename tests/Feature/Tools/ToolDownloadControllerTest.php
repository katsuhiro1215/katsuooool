<?php

use App\Services\Tools\ToolDownloadService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('a validly signed download URL streams the file', function () {
    Storage::disk('local')->put('tool-tmp/batch-1/a.jpg', 'fake-image-a');

    $service = new ToolDownloadService;
    $url = $service->signedUrl('tool-tmp/batch-1/a.jpg', 'photo-a.jpg');

    $response = $this->get($url);

    $response->assertSuccessful();
});

test('a request without a valid signature is rejected', function () {
    Storage::disk('local')->put('tool-tmp/batch-1/a.jpg', 'fake-image-a');

    $response = $this->get('/tool-download/'.base64_encode('tool-tmp/batch-1/a.jpg').'?download_name=photo-a.jpg');

    $response->assertForbidden();
});

test('a signed URL for a path with a multibyte filename round-trips successfully', function () {
    $path = 'tool-tmp/batch-1/画像ファイル.jpg';
    Storage::disk('local')->put($path, 'fake-image-a');

    $service = new ToolDownloadService;
    $url = $service->signedUrl($path, '写真.jpg');

    $response = $this->get($url);

    $response->assertSuccessful();
});

test('a path outside tool-tmp is rejected even with a valid signature', function () {
    $service = new ToolDownloadService;
    $url = $service->signedUrl('../.env', 'stolen.env');

    $response = $this->get($url);

    $response->assertNotFound();
});
