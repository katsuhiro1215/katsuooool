<?php

use App\Exceptions\Tools\ProcessingBusyException;
use App\Support\ImageProcessingGate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('runs the work and returns its result', function () {
    expect(ImageProcessingGate::run(fn () => 'ok'))->toBe('ok');
});

test('throws when every slot is taken', function () {
    config()->set('image_tools.concurrency', 1);

    $result = ImageProcessingGate::run(function () {
        expect(fn () => ImageProcessingGate::run(fn () => 'inner'))
            ->toThrow(ProcessingBusyException::class);

        return 'outer';
    });

    expect($result)->toBe('outer');
});

test('uses a second slot when the first is taken', function () {
    config()->set('image_tools.concurrency', 2);

    $result = ImageProcessingGate::run(fn () => ImageProcessingGate::run(fn () => 'both'));

    expect($result)->toBe('both');
});

test('releases the slot even when the work throws', function () {
    config()->set('image_tools.concurrency', 1);

    expect(fn () => ImageProcessingGate::run(fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class);

    expect(ImageProcessingGate::run(fn () => 'again'))->toBe('again');
});

test('an image tool request gets 503 while every slot is busy, and works afterwards', function () {
    Storage::fake('local');
    config()->set('image_tools.concurrency', 1);

    $post = fn () => test()
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('image-convert.process'), [
            'files' => [UploadedFile::fake()->image('a.jpg', 50, 50)],
            'format' => 'webp',
        ]);

    ImageProcessingGate::run(function () use ($post) {
        $post()->assertStatus(503)
            ->assertJsonPath('message', '現在アクセスが集中しています。少し待ってから、もう一度お試しください。');
    });

    $post()->assertSuccessful();
});
