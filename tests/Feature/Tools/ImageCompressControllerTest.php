<?php

use App\Models\ToolEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('the image-compress page renders', function () {
    $response = $this->get(route('image-compress.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageCompress'));
});

test('posting valid images returns compressed download links', function () {
    $files = [
        UploadedFile::fake()->image('photo-a.jpg', 800, 600),
        UploadedFile::fake()->image('photo-b.png', 400, 300),
    ];

    $response = $this->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.original_name', 'photo-a.jpg');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.status', 'success');
    $response->assertJsonPath('batch_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));

    expect(ToolEvent::where('tool_slug', 'image-compress')->where('event_type', 'process')->exists())->toBeTrue();
});

test('posting a single valid image does not return a batch download url', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 800, 600)];

    $response = $this->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('batch_download_url', null);
});

test('posting a disallowed file type is reported as rejected, not a fatal error', function () {
    $files = [
        UploadedFile::fake()->image('good.jpg', 800, 600),
        UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ];

    $response = $this->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.original_name', 'not-an-image.txt');
    $response->assertJsonPath('results.1.status', 'rejected');
});

test('posting more than 10 files is rejected with a validation error', function () {
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = $this->post(route('image-compress.process'), ['files' => $files]);

    $response->assertStatus(422);
});
