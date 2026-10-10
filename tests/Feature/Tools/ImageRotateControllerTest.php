<?php

use App\Models\ToolEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postImageRotate(array $files, string $direction = 'left')
{
    return test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-rotate.process'), ['files' => $files, 'direction' => $direction]);
}

test('the image-rotate page renders', function () {
    $response = $this->get(route('image-rotate.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageRotate'));
});

test('posting valid images rotates and returns download links with swapped dimensions', function () {
    $files = [
        UploadedFile::fake()->image('photo-a.jpg', 100, 60),
        UploadedFile::fake()->image('photo-b.png', 100, 60),
    ];

    $response = postImageRotate($files, 'left');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.0.width', 60);
    $response->assertJsonPath('results.0.height', 100);
    $response->assertJsonPath('batch_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));

    expect(ToolEvent::where('tool_slug', 'image-rotate')->where('event_type', 'process')->exists())->toBeTrue();
});

test('rotating 180 degrees keeps the same dimensions', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 100, 60)];

    $response = postImageRotate($files, '180');

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.0.width', 100);
    $response->assertJsonPath('results.0.height', 60);
});

test('posting a single valid image does not return a batch download url', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 100, 60)];

    $response = postImageRotate($files, 'right');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('batch_download_url', null);
});

test('an invalid direction is rejected with a validation error', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 100, 60)];

    $response = postImageRotate($files, 'upside-down');

    $response->assertStatus(422);
});

test('posting a disallowed file type is reported as rejected, not a fatal error', function () {
    $files = [
        UploadedFile::fake()->image('good.jpg', 100, 60),
        UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ];

    $response = postImageRotate($files, 'left');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.original_name', 'not-an-image.txt');
    $response->assertJsonPath('results.1.status', 'rejected');
});

test('an image exceeding the pixel-dimension ceiling is reported as failed', function () {
    $bigPath = sys_get_temp_dir().'/'.uniqid('image-rotate-big-', true).'.jpg';
    $gdImage = imagecreatetruecolor(6000, 4200);
    imagejpeg($gdImage, $bigPath, 90);
    imagedestroy($gdImage);

    $bigFile = new UploadedFile($bigPath, 'big.jpg', 'image/jpeg', null, true);

    $response = postImageRotate([$bigFile], 'left');

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'failed');
    $response->assertJsonPath('results.0.reason', '画像の解像度が大きすぎます。');

    expect(ToolEvent::where('tool_slug', 'image-rotate')->where('event_type', 'failed')->exists())->toBeTrue();

    @unlink($bigPath);
});

test('a backslash in the original filename is sanitized so the download link does not 500', function () {
    $file = UploadedFile::fake()->image('a\\b.jpg', 100, 60);

    $response = postImageRotate([$file], 'left');

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');

    $downloadUrl = $response->json('results.0.download_url');
    expect($downloadUrl)->not->toContain('\\');

    $downloadResponse = $this->get($downloadUrl);
    $downloadResponse->assertSuccessful();
});

test('duplicate download names are de-duplicated so the zip does not lose files', function () {
    $files = [
        UploadedFile::fake()->image('photo.jpg', 100, 60),
        UploadedFile::fake()->image('photo.jpeg', 100, 60),
    ];

    $response = postImageRotate($files, 'left');

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

    $response = postImageRotate($files, 'left');

    $response->assertStatus(422);
});

test('a free user cannot upload more than 10 files at once', function () {
    $user = User::factory()->create();
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageRotate($files, 'left');

    $response->assertStatus(422);
});

test('a premium user can upload up to 20 files at once', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_rotate',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 4))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageRotate($files, 'left');

    $response->assertSuccessful();
    $response->assertJsonCount(4, 'results');
});
