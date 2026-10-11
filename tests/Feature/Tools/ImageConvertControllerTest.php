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

test('a free (unauthenticated) user receives maxFiles of 10', function () {
    $response = $this->get(route('image-convert.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageConvert')->where('maxFiles', 10));
});

test('a premium user receives maxFiles of 20', function () {
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
    $response->assertInertia(fn ($page) => $page->component('ImageConvert')->where('maxFiles', 20));
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

test('a free user cannot upload more than 10 files at once', function () {
    $user = User::factory()->create();
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageConvert($files);

    $response->assertStatus(422);
});

test('a premium user can upload up to 20 files at once', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_convert',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 20))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageConvert($files);

    $response->assertSuccessful();
    $response->assertJsonCount(20, 'results');
});

test('a premium user posting 21 files is rejected with a validation error', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_convert_21',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 21))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 50, 50))
        ->all();

    $this->actingAs($user);

    postImageConvert($files, 'webp')->assertStatus(422);
});

test('the convert page receives the AVIF limit as a prop', function () {
    $this->get(route('image-convert.show'))->assertInertia(fn ($page) => $page
        ->component('ImageConvert')
        ->where('maxFiles', 10)
        ->where('avifMaxFiles', 2)
    );
});

test('a free user converting 3 files to avif is rejected, but 3 files to webp is accepted', function () {
    $files = fn () => collect(range(1, 3))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 50, 50))
        ->all();

    postImageConvert($files(), 'avif')->assertStatus(422);
    postImageConvert($files(), 'webp')->assertSuccessful();
});

test('a free user converting 2 files to avif passes the file-count validation', function () {
    $files = collect(range(1, 2))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 50, 50))
        ->all();

    postImageConvert($files, 'avif')->assertSuccessful();
});

test('a premium user can convert 5 files to avif but not 6', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_convert_avif',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $make = fn (int $n) => collect(range(1, $n))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 50, 50))
        ->all();

    $this->actingAs($user);

    postImageConvert($make(6), 'avif')->assertStatus(422);
    postImageConvert($make(5), 'avif')->assertSuccessful();
});

function heicFixture(string $name = 'IMG_0001.heic'): UploadedFile
{
    return new UploadedFile(base_path('tests/fixtures/sample.heic'), $name, 'image/heic', null, true);
}

$heicSupported = class_exists(Imagick::class) && in_array('HEIC', Imagick::queryFormats(), true);

test('a HEIC photo can be converted to jpeg, png and webp', function (string $format) {
    $response = postImageConvert([heicFixture()], $format);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.0.download_name', 'IMG_0001.'.$format);
})->with(['jpg', 'png', 'webp'])->skip(! $heicSupported, 'この環境の Imagick は HEIC に対応していない');

test('a HEIC photo is rejected by tools that do not accept HEIC (rotate)', function () {
    // 圧縮・リサイズ（M5-3）と形式変換は HEIC を受け付ける。回転・切り抜きなどは、まだ受け付けない。
    $response = test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-rotate.process'), ['files' => [heicFixture()], 'direction' => 'left']);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'rejected');
});

test('an image above the AVIF pixel ceiling is refused for avif but accepted for webp', function () {
    // 3200x2600 = 8.32M 画素。AVIF は画素数に比例してメモリを使うため 800 万画素まで。
    $path = sys_get_temp_dir().'/'.uniqid('image-convert-avif-ceiling-', true).'.jpg';
    $gdImage = imagecreatetruecolor(3200, 2600);
    imagejpeg($gdImage, $path, 90);
    imagedestroy($gdImage);

    $make = fn () => new UploadedFile($path, 'wide.jpg', 'image/jpeg', null, true);

    $avif = postImageConvert([$make()], 'avif');
    $avif->assertSuccessful();
    $avif->assertJsonPath('results.0.status', 'failed');
    $avif->assertJsonPath('results.0.reason', '画像の解像度が大きすぎます。AVIF への変換は800万画素までです。');

    $webp = postImageConvert([$make()], 'webp');
    $webp->assertJsonPath('results.0.status', 'success');
});

test('a free user can upload exactly 10 files at once', function () {
    $files = collect(range(1, 10))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 50, 50))
        ->all();

    $response = postImageConvert($files, 'webp');

    $response->assertSuccessful();
    $response->assertJsonCount(10, 'results');
});
