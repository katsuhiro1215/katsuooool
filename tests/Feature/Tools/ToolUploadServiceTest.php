<?php

use App\Exceptions\Tools\TooManyFilesException;
use App\Services\Tools\ToolUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('store saves valid files under a batch directory on the local disk', function () {
    $service = new ToolUploadService;

    $file = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(50);

    $result = $service->store(
        uploadedFiles: [$file],
        allowedMimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    );

    expect($result->batchId)->not->toBeEmpty();
    expect($result->files)->toHaveCount(1);
    expect($result->rejected)->toBe([]);

    Storage::disk('local')->assertExists($result->files[0]->path);
    expect($result->files[0]->originalName)->toBe('photo.jpg');
});

test('store rejects a file that exceeds the size limit', function () {
    $service = new ToolUploadService;

    $tooLarge = UploadedFile::fake()->image('big.jpg')->size(11 * 1024);

    $result = $service->store(
        uploadedFiles: [$tooLarge],
        allowedMimeTypes: ['image/jpeg'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    );

    expect($result->files)->toBe([]);
    expect($result->rejected)->toHaveKey(0);
});

test('store rejects a file whose real content type is not allowed', function () {
    $service = new ToolUploadService;

    $textFile = UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain');

    $result = $service->store(
        uploadedFiles: [$textFile],
        allowedMimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    );

    expect($result->files)->toBe([]);
    expect($result->rejected)->toHaveKey(0);
});

test('store rejects a file whose extension lies about its real content', function () {
    $service = new ToolUploadService;

    $tmpPath = tempnam(sys_get_temp_dir(), 'fake_jpg_');
    file_put_contents($tmpPath, 'this is plain text, not an image');

    $spoofedFile = new UploadedFile(
        $tmpPath,
        'evil.jpg',
        'image/jpeg', // client-declared type — must be ignored
        null,
        true, // test mode, so Laravel doesn't require a real HTTP upload
    );

    $result = $service->store(
        uploadedFiles: [$spoofedFile],
        allowedMimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    );

    expect($result->files)->toBe([]);
    expect($result->rejected)->toHaveKey(0);

    @unlink($tmpPath);
});

test('store throws when the number of files exceeds the maximum', function () {
    $service = new ToolUploadService;

    $files = collect(range(1, 11))
        ->map(fn () => UploadedFile::fake()->image('photo.jpg')->size(10))
        ->all();

    expect(fn () => $service->store(
        uploadedFiles: $files,
        allowedMimeTypes: ['image/jpeg'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    ))->toThrow(TooManyFilesException::class);
});
