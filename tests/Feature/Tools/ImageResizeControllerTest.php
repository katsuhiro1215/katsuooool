<?php

use App\Models\ToolEvent;
use App\Models\User;
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

test('posting a disallowed file type is reported as rejected, not a fatal error', function () {
    $files = [
        UploadedFile::fake()->image('good.jpg', 800, 600),
        UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ];

    $response = postImageResize($files, width: 200);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.original_name', 'not-an-image.txt');
    $response->assertJsonPath('results.1.status', 'rejected');
});

test('an image exceeding the pixel-dimension ceiling is reported as failed', function () {
    // 6000x4200 = 25.2M pixels, just over the 25M-pixel MAX_PIXELS ceiling.
    $bigPath = sys_get_temp_dir().'/'.uniqid('image-resize-big-', true).'.jpg';
    $gdImage = imagecreatetruecolor(6000, 4200);
    imagejpeg($gdImage, $bigPath, 90);
    imagedestroy($gdImage);

    $bigFile = new UploadedFile($bigPath, 'big.jpg', 'image/jpeg', null, true);

    $response = postImageResize([$bigFile], width: 200);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'failed');
    $response->assertJsonPath('results.0.reason', '画像の解像度が大きすぎます。');

    expect(ToolEvent::where('tool_slug', 'image-resize')->where('event_type', 'failed')->exists())->toBeTrue();

    @unlink($bigPath);
});

test('an output resolution that would exceed the pixel ceiling is reported as failed', function () {
    // A tiny 10x2500 input (25,000 px, well under every input-side ceiling)
    // requested at width=8000 with height auto-calculated proportionally
    // projects to 8000x2,000,000 — 16B output pixels — which must be
    // rejected before the expensive resize is even attempted.
    $tallPath = sys_get_temp_dir().'/'.uniqid('image-resize-tall-', true).'.jpg';
    $gdImage = imagecreatetruecolor(10, 2500);
    imagejpeg($gdImage, $tallPath, 90);
    imagedestroy($gdImage);

    $tallFile = new UploadedFile($tallPath, 'tall.jpg', 'image/jpeg', null, true);

    $response = postImageResize([$tallFile], width: 8000);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'failed');
    $response->assertJsonPath('results.0.reason', '画像のリサイズに失敗しました。');

    @unlink($tallPath);
});

test('a backslash in the original filename is sanitized so the download link does not 500', function () {
    $file = UploadedFile::fake()->image('a\\b.jpg', 800, 600);

    $response = postImageResize([$file], width: 200);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');

    $downloadUrl = $response->json('results.0.download_url');
    expect($downloadUrl)->not->toContain('\\');

    $downloadResponse = $this->get($downloadUrl);
    $downloadResponse->assertSuccessful();
});

test('duplicate download names are de-duplicated so the zip does not lose files', function () {
    $files = [
        UploadedFile::fake()->image('photo.jpg', 800, 600),
        UploadedFile::fake()->image('photo.jpeg', 400, 300),
    ];

    $response = postImageResize($files, width: 200);

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
    $response = postImageResize($files, width: 200);

    $response->assertStatus(422);
});

test('a premium user can upload up to 10 files at once', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_resize',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 4))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageResize($files, width: 200);

    $response->assertSuccessful();
    $response->assertJsonCount(4, 'results');
});
