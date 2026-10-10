<?php

use App\Models\ToolEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postImageColorExtract(array $files)
{
    return test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-color-extract.process'), ['files' => $files]);
}

test('the image-color-extract page renders', function () {
    $response = $this->get(route('image-color-extract.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageColorExtract'));
});

test('posting valid images returns extracted colors without a download url', function () {
    $files = [
        UploadedFile::fake()->image('photo-a.jpg', 400, 300),
        UploadedFile::fake()->image('photo-b.png', 400, 300),
    ];

    $response = postImageColorExtract($files);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.status', 'success');
    $response->assertJsonMissingPath('batch_download_url');

    $colors = $response->json('results.0.colors');
    expect($colors)->toBeArray();
    expect(count($colors))->toBeGreaterThanOrEqual(1);
    expect(count($colors))->toBeLessThanOrEqual(6);
    expect($colors[0])->toHaveKeys(['hex', 'rgb']);

    expect(ToolEvent::where('tool_slug', 'image-color-extract')->where('event_type', 'process')->exists())->toBeTrue();
});

test('posting a disallowed file type is reported as rejected, not a fatal error', function () {
    $files = [
        UploadedFile::fake()->image('good.jpg', 400, 300),
        UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ];

    $response = postImageColorExtract($files);

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'results');
    $response->assertJsonPath('results.0.status', 'success');
    $response->assertJsonPath('results.1.original_name', 'not-an-image.txt');
    $response->assertJsonPath('results.1.status', 'rejected');
});

test('an image exceeding the pixel-dimension ceiling is reported as failed', function () {
    $bigPath = sys_get_temp_dir().'/'.uniqid('image-color-extract-big-', true).'.jpg';
    $gdImage = imagecreatetruecolor(6000, 4200);
    imagejpeg($gdImage, $bigPath, 90);
    imagedestroy($gdImage);

    $bigFile = new UploadedFile($bigPath, 'big.jpg', 'image/jpeg', null, true);

    $response = postImageColorExtract([$bigFile]);

    $response->assertSuccessful();
    $response->assertJsonPath('results.0.status', 'failed');
    $response->assertJsonPath('results.0.reason', '画像の解像度が大きすぎます。');

    expect(ToolEvent::where('tool_slug', 'image-color-extract')->where('event_type', 'failed')->exists())->toBeTrue();

    @unlink($bigPath);
});

test('posting more than 10 files is rejected with a validation error', function () {
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $response = postImageColorExtract($files);

    $response->assertStatus(422);
});

test('a free user cannot upload more than 10 files at once', function () {
    $user = User::factory()->create();
    $files = collect(range(1, 11))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageColorExtract($files);

    $response->assertStatus(422);
});

test('a premium user can upload up to 20 files at once', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_color_extract',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_monthly',
        'quantity' => 1,
    ]);
    $files = collect(range(1, 20))
        ->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 100, 100))
        ->all();

    $this->actingAs($user);
    $response = postImageColorExtract($files);

    $response->assertSuccessful();
    $response->assertJsonCount(20, 'results');
});
