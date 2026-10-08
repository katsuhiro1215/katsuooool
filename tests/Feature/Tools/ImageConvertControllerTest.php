<?php

use App\Models\ToolEvent;
use App\Models\User;
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

test('a free (unauthenticated) user receives maxFiles of 3', function () {
    $response = $this->get(route('image-convert.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageConvert')->where('maxFiles', 3));
});

test('a premium user receives maxFiles of 10', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_convert',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('image-convert.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageConvert')->where('maxFiles', 10));
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
    $response->assertJsonPath('results.0.download_name', 'photo-a.webp');
    $response->assertJsonPath('results.1.download_name', 'photo-b.webp');
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

test('posting a disallowed file type is reported as rejected, not a fatal error', function () {
    $files = [
        UploadedFile::fake()->image('good.jpg', 400, 300),
        UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ];

    $response = postImageConvert($files, 'webp');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.original_name', 'not-an-image.txt');
    $response->assertJsonPath('results.1.status', 'rejected');
});

test('an image exceeding the pixel-dimension ceiling is reported as failed', function () {
    // 6000x4200 = 25.2M pixels, just over the 25M-pixel MAX_PIXELS ceiling.
    $bigPath = sys_get_temp_dir().'/'.uniqid('image-convert-big-', true).'.jpg';
    $gdImage = imagecreatetruecolor(6000, 4200);
    imagejpeg($gdImage, $bigPath, 90);
    imagedestroy($gdImage);

    $bigFile = new UploadedFile($bigPath, 'big.jpg', 'image/jpeg', null, true);

    $response = postImageConvert([$bigFile], 'webp');

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'failed');
    $response->assertJsonPath('results.0.reason', '画像の解像度が大きすぎます。');

    expect(ToolEvent::where('tool_slug', 'image-convert')->where('event_type', 'failed')->exists())->toBeTrue();

    @unlink($bigPath);
});

test('a backslash in the original filename is sanitized so the download link does not 500', function () {
    $file = UploadedFile::fake()->image('a\\b.jpg', 400, 300);

    $response = postImageConvert([$file], 'webp');

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');

    $downloadUrl = $response->json('results.0.download_url');
    expect($downloadUrl)->not->toContain('\\');

    $downloadResponse = $this->get($downloadUrl);
    $downloadResponse->assertSuccessful();
});

test('duplicate download names are de-duplicated so the zip does not lose files', function () {
    $files = [
        UploadedFile::fake()->image('photo.jpg', 400, 300),
        UploadedFile::fake()->image('photo.jpeg', 400, 300),
    ];

    $response = postImageConvert($files, 'webp');

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');

    $results = $response->json('results');
    expect($results[0]['status'])->toBe('success');
    expect($results[1]['status'])->toBe('success');
    expect($results[0]['download_url'])->not->toBe($results[1]['download_url']);
});

test('a free user cannot upload more than 3 files at once', function () {
    $user = User::factory()->create();
    $files = collect(range(1, 4))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageConvert($files);

    $response->assertStatus(422);
});

test('a premium user can upload up to 10 files at once', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_convert',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 4))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageConvert($files);

    $response->assertSuccessful();
    $response->assertJsonCount(4, 'results');
});
