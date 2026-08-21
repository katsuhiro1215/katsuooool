<?php

use App\Models\ToolEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postImageCrop(UploadedFile $file, int $x, int $y, int $width, int $height)
{
    return test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-crop.process'), [
            'file' => $file,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ]);
}

test('the image-crop page renders', function () {
    $response = $this->get(route('image-crop.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('ImageCrop'));
});

test('posting a valid crop rectangle returns a download link with the cropped dimensions', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 200, 100);

    $response = postImageCrop($file, 10, 10, 80, 60);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('width', 80);
    $response->assertJsonPath('height', 60);
    $response->assertJsonPath('download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));

    expect(ToolEvent::where('tool_slug', 'image-crop')->where('event_type', 'process')->exists())->toBeTrue();
});

test('a crop rectangle that exceeds the image bounds is reported as failed', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 60);

    $response = postImageCrop($file, 50, 0, 80, 60);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'failed');
    $response->assertJsonPath('reason', '切り抜き範囲が画像のサイズを超えています。');
});

test('posting a disallowed file type is rejected', function () {
    $file = UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain');

    $response = postImageCrop($file, 0, 0, 10, 10);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'rejected');
});

test('an image exceeding the pixel-dimension ceiling is reported as failed', function () {
    $bigPath = sys_get_temp_dir().'/'.uniqid('image-crop-big-', true).'.jpg';
    $gdImage = imagecreatetruecolor(6000, 4200);
    imagejpeg($gdImage, $bigPath, 90);
    imagedestroy($gdImage);

    $bigFile = new UploadedFile($bigPath, 'big.jpg', 'image/jpeg', null, true);

    $response = postImageCrop($bigFile, 0, 0, 100, 100);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'failed');
    $response->assertJsonPath('reason', '画像の解像度が大きすぎます。');

    @unlink($bigPath);
});

test('a missing width parameter is rejected with a validation error', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 60);

    $response = test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-crop.process'), [
            'file' => $file,
            'x' => 0,
            'y' => 0,
            'height' => 10,
        ]);

    $response->assertStatus(422);
});
