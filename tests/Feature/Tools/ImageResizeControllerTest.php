<?php

use App\Models\ToolEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postImageResize(array $files, ?int $width = 200, ?int $height = null)
{
    $payload = ['files' => $files];

    if ($width !== null) {
        $payload['width'] = $width;
    }

    if ($height !== null) {
        $payload['height'] = $height;
    }

    return test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-resize.process'), $payload);
}

test('the image-resize page renders', function () {
    $response = $this->get(route('image-resize.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageResize'));
});

test('posting valid images with a width resizes and returns download links', function () {
    $files = [
        UploadedFile::fake()->image('photo-a.jpg', 800, 600),
        UploadedFile::fake()->image('photo-b.png', 800, 600),
    ];

    $response = postImageResize($files, width: 200);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.0.width', 200);
    $response->assertJsonPath('results.0.height', 150);
    $response->assertJsonPath('batch_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));

    expect(ToolEvent::where('tool_slug', 'image-resize')->where('event_type', 'process')->exists())->toBeTrue();
});

test('omitting both width and height is rejected with a validation error', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 800, 600)];

    $response = postImageResize($files, width: null, height: null);

    $response->assertStatus(422);
});

test('a non-positive width is rejected with a validation error', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 800, 600)];

    $response = postImageResize($files, width: 0);

    $response->assertStatus(422);
});

test('posting more than 10 files is rejected with a validation error', function () {
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = postImageResize($files, width: 100);

    $response->assertStatus(422);
});
