<?php

use App\Models\ToolEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postOgpImage(array $files)
{
    return test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('ogp-image.process'), ['files' => $files]);
}

test('the ogp-image page renders', function () {
    $response = $this->get(route('ogp-image.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('OgpImage'));
});

test('posting valid images returns 1200x630 download links', function () {
    $files = [
        UploadedFile::fake()->image('photo-a.jpg', 2000, 800),
        UploadedFile::fake()->image('photo-b.png', 500, 1500),
    ];

    $response = postOgpImage($files);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.0.width', 1200);
    $response->assertJsonPath('results.0.height', 630);
    $response->assertJsonPath('batch_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));

    expect(ToolEvent::where('tool_slug', 'ogp-image')->where('event_type', 'process')->exists())->toBeTrue();
});

test('posting a single valid image does not return a batch download url', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 2000, 800)];

    $response = postOgpImage($files);

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('batch_download_url', null);
});

test('posting a disallowed file type is reported as rejected, not a fatal error', function () {
    $files = [
        UploadedFile::fake()->image('good.jpg', 2000, 800),
        UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ];

    $response = postOgpImage($files);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.original_name', 'not-an-image.txt');
    $response->assertJsonPath('results.1.status', 'rejected');
});

test('an image exceeding the pixel-dimension ceiling is reported as failed', function () {
    $bigPath = sys_get_temp_dir().'/'.uniqid('ogp-image-big-', true).'.jpg';
    $gdImage = imagecreatetruecolor(6000, 4200);
    imagejpeg($gdImage, $bigPath, 90);
    imagedestroy($gdImage);

    $bigFile = new UploadedFile($bigPath, 'big.jpg', 'image/jpeg', null, true);

    $response = postOgpImage([$bigFile]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'failed');
    $response->assertJsonPath('results.0.reason', '画像の解像度が大きすぎます。');

    expect(ToolEvent::where('tool_slug', 'ogp-image')->where('event_type', 'failed')->exists())->toBeTrue();

    @unlink($bigPath);
});

test('a backslash in the original filename is sanitized so the download link does not 500', function () {
    $file = UploadedFile::fake()->image('a\\b.jpg', 2000, 800);

    $response = postOgpImage([$file]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');

    $downloadUrl = $response->json('results.0.download_url');
    expect($downloadUrl)->not->toContain('\\');

    $downloadResponse = $this->get($downloadUrl);
    $downloadResponse->assertSuccessful();
});

test('duplicate download names are de-duplicated so the zip does not lose files', function () {
    $files = [
        UploadedFile::fake()->image('photo.jpg', 2000, 800),
        UploadedFile::fake()->image('photo.jpeg', 2000, 800),
    ];

    $response = postOgpImage($files);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');

    $results = $response->json('results');
    expect($results[0]['status'])->toBe('success');
    expect($results[1]['status'])->toBe('success');
    expect($results[0]['download_url'])->not->toBe($results[1]['download_url']);
});

test('posting more than 10 files is rejected with a validation error', function () {
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = postOgpImage($files);

    $response->assertStatus(422);
});

test('a free user cannot upload more than 10 files at once', function () {
    $user = User::factory()->create();
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postOgpImage($files);

    $response->assertStatus(422);
});

test('a premium user can upload up to 20 files at once', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_ogp',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 20))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postOgpImage($files);

    $response->assertSuccessful();
    $response->assertJsonCount(20, 'results');
});
