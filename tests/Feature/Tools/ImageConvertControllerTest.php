<?php

use App\Models\ToolEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postImageConvert(array $files, string $format = 'webp')
{
    return test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-convert.process'), ['files' => $files, 'format' => $format]);
}

test('the image-convert page renders', function () {
    $response = $this->get(route('image-convert.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageConvert'));
});

test('posting valid images returns converted download links in the requested format', function () {
    $files = [
        UploadedFile::fake()->image('photo-a.jpg', 400, 300),
        UploadedFile::fake()->image('photo-b.png', 400, 300),
    ];

    $response = postImageConvert($files, 'webp');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.status', 'success');
    $response->assertJsonPath('batch_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));

    expect(ToolEvent::where('tool_slug', 'image-convert')->where('event_type', 'process')->exists())->toBeTrue();
});

test('posting a single valid image does not return a batch download url', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 400, 300)];

    $response = postImageConvert($files, 'png');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('batch_download_url', null);
});

test('an invalid target format is rejected with a validation error', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 400, 300)];

    $response = postImageConvert($files, 'gif');

    $response->assertStatus(422);
});

test('posting more than 10 files is rejected with a validation error', function () {
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = postImageConvert($files, 'webp');

    $response->assertStatus(422);
});
