<?php

use App\Models\ToolEvent;
use App\Models\User;
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

test('a free (unauthenticated) user receives maxFiles of 10', function () {
    $response = $this->get(route('image-compress.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageCompress')->where('maxFiles', 10));
});

test('a premium user receives maxFiles of 20', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_compress',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('image-compress.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageCompress')->where('maxFiles', 20));
});

test('posting valid images returns compressed download links', function () {
    $files = [
        UploadedFile::fake()->image('photo-a.jpg', 800, 600),
        UploadedFile::fake()->image('photo-b.png', 400, 300),
    ];

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => $files]);

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

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => $files]);

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

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.original_name', 'not-an-image.txt');
    $response->assertJsonPath('results.1.status', 'rejected');
});

test('a per-file compress failure is reported as failed without failing the whole request', function () {
    $goodSource = UploadedFile::fake()->image('good.jpg', 800, 600);

    $corruptSource = UploadedFile::fake()->image('corrupt.png', 800, 600);
    $corruptBytes = file_get_contents($corruptSource->getRealPath());
    $truncatedPath = sys_get_temp_dir().'/'.uniqid('image-compress-truncated-', true).'.png';
    file_put_contents($truncatedPath, substr($corruptBytes, 0, (int) (strlen($corruptBytes) / 2)));

    $corruptFile = new UploadedFile(
        $truncatedPath,
        'corrupt.png',
        'image/png',
        null,
        true,
    );

    $files = [$goodSource, $corruptFile];

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');

    $results = $response->json('results');
    $goodResult = collect($results)->firstWhere('original_name', 'good.jpg');
    $corruptResult = collect($results)->firstWhere('original_name', 'corrupt.png');

    expect($goodResult['status'])->toBe('success');
    expect($corruptResult['status'])->toBe('failed');
    expect($corruptResult['reason'])->toBeString()->not->toBeEmpty();

    @unlink($truncatedPath);
});

test('when recompression would be larger than the original, the original is returned with a 0% reduction', function () {
    // A 1x1 PNG is already minimal; re-encoding it through GD produces a
    // slightly larger file (extra header bytes), reproducing the "negative
    // reduction" scenario deterministically.
    $tinyPath = sys_get_temp_dir().'/'.uniqid('image-compress-tiny-', true).'.png';
    $gdImage = imagecreatetruecolor(1, 1);
    imagepng($gdImage, $tinyPath);
    imagedestroy($gdImage);
    $originalSize = filesize($tinyPath);

    $tinyFile = new UploadedFile(
        $tinyPath,
        'tiny.png',
        'image/png',
        null,
        true,
    );

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => [$tinyFile]]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.0.output_bytes', $originalSize);
    $response->assertJsonPath('results.0.reduction_percent', 0);

    @unlink($tinyPath);
});

test('duplicate download names are de-duplicated so the zip does not lose files', function () {
    $files = [
        UploadedFile::fake()->image('photo.jpg', 800, 600),
        UploadedFile::fake()->image('photo.jpeg', 400, 300),
    ];

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');

    $results = $response->json('results');
    expect($results[0]['status'])->toBe('success');
    expect($results[1]['status'])->toBe('success');
    expect($results[0]['download_url'])->not->toBe($results[1]['download_url']);

    $batchDownloadUrl = $response->json('batch_download_url');
    expect($batchDownloadUrl)->toBeString();

    // Extract the zip path from the signed URL's {token} route segment and
    // inspect the archive directly.
    preg_match('#/tool-download/([^/?]+)#', $batchDownloadUrl, $matches);
    $zipPath = base64_decode(strtr($matches[1], '-_', '+/'));

    $disk = Storage::disk('local');
    $zip = new ZipArchive;
    $zip->open($disk->path($zipPath));
    expect($zip->numFiles)->toBe(2);
    $zip->close();
});

test('an image exceeding the pixel-dimension ceiling is reported as failed', function () {
    // 6000x4200 = 25.2M pixels, just over the 25M-pixel MAX_PIXELS ceiling,
    // while staying well under the 10MB file-size limit.
    $bigPath = sys_get_temp_dir().'/'.uniqid('image-compress-big-', true).'.jpg';
    $gdImage = imagecreatetruecolor(6000, 4200);
    imagejpeg($gdImage, $bigPath, 90);
    imagedestroy($gdImage);

    $bigFile = new UploadedFile(
        $bigPath,
        'big.jpg',
        'image/jpeg',
        null,
        true,
    );

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => [$bigFile]]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'failed');
    $response->assertJsonPath('results.0.reason', '画像の解像度が大きすぎます。');

    expect(ToolEvent::where('tool_slug', 'image-compress')->where('event_type', 'failed')->exists())->toBeTrue();

    @unlink($bigPath);
});

test('a backslash in the original filename is sanitized so the download link does not 500', function () {
    $file = UploadedFile::fake()->image('a\\b.jpg', 800, 600);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => [$file]]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');

    $downloadUrl = $response->json('results.0.download_url');
    expect($downloadUrl)->not->toContain('\\');

    $downloadResponse = $this->get($downloadUrl);
    $downloadResponse->assertSuccessful();
});

test('posting more than 10 files is rejected with a validation error', function () {
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->post(route('image-compress.process'), ['files' => $files]);

    $response->assertStatus(422);
});

test('a free user cannot upload more than 10 files at once', function () {
    $user = User::factory()->create();
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files]);

    $response->assertStatus(422);
});

test('a premium user can upload up to 20 files at once', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_compress',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 20))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(20, 'results');
});

test('a free user can upload exactly 10 files at once', function () {
    $files = collect(range(1, 10))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 50, 50))
        ->all();

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files]);

    $response->assertSuccessful();
    $response->assertJsonCount(10, 'results');
});

test('an invalid compression level is rejected with a validation error', function () {
    $files = [UploadedFile::fake()->image('photo.jpg', 100, 100)];

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files, 'level' => 'extreme']);

    $response->assertStatus(422);
});

test('each valid compression level is accepted', function (string $level) {
    $files = [UploadedFile::fake()->image('photo.jpg', 100, 100)];

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-compress.process'), ['files' => $files, 'level' => $level]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');
})->with(['light', 'recommended', 'strong']);
