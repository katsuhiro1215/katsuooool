<?php

use App\Models\ToolEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postFaviconGenerate(UploadedFile $file)
{
    return test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('favicon-generate.process'), ['file' => $file]);
}

test('the favicon-generate page renders', function () {
    $response = $this->get(route('favicon-generate.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('FaviconGenerate'));
});

test('posting a valid image returns a zip download url containing all six sizes', function () {
    $file = UploadedFile::fake()->image('logo.png', 1000, 1000);

    $response = postFaviconGenerate($file);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));

    expect(ToolEvent::where('tool_slug', 'favicon-generate')->where('event_type', 'process')->exists())->toBeTrue();

    $downloadUrl = $response->json('download_url');
    $downloadResponse = $this->get($downloadUrl);
    $downloadResponse->assertSuccessful();

    $disk = Storage::disk('local');
    preg_match('#/tool-download/([^/?]+)#', $downloadUrl, $matches);
    $zipPath = base64_decode(strtr($matches[1], '-_', '+/'));

    $zip = new ZipArchive;
    $zip->open($disk->path($zipPath));
    expect($zip->numFiles)->toBe(6);
    $zip->close();
});

test('posting a disallowed file type returns a validation error', function () {
    $file = UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain');

    $response = postFaviconGenerate($file);

    $response->assertStatus(422);
});

test('posting a file exceeding the size limit returns a validation error', function () {
    $file = UploadedFile::fake()->create('huge.jpg', 11 * 1024, 'image/jpeg');

    $response = postFaviconGenerate($file);

    $response->assertStatus(422);
});

test('an image exceeding the pixel-dimension ceiling is rejected', function () {
    $bigPath = sys_get_temp_dir().'/'.uniqid('favicon-big-', true).'.jpg';
    $gdImage = imagecreatetruecolor(6000, 4200);
    imagejpeg($gdImage, $bigPath, 90);
    imagedestroy($gdImage);

    $bigFile = new UploadedFile($bigPath, 'big.jpg', 'image/jpeg', null, true);

    $response = postFaviconGenerate($bigFile);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'failed');
    $response->assertJsonPath('reason', '画像の解像度が大きすぎます。');

    expect(ToolEvent::where('tool_slug', 'favicon-generate')->where('event_type', 'failed')->exists())->toBeTrue();

    @unlink($bigPath);
});
