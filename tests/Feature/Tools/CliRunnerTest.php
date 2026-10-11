<?php

use App\Services\Tools\CliRunner;

test('has detects an existing and a missing binary', function () {
    $runner = new CliRunner;

    expect($runner->has('php'))->toBeTrue()
        ->and($runner->has('definitely-not-a-real-binary-xyz'))->toBeFalse();
});

test('run returns the exit code of the command', function () {
    $runner = new CliRunner;

    expect($runner->run([PHP_BINARY, '-r', 'exit(0);']))->toBe(0)
        ->and($runner->run([PHP_BINARY, '-r', 'exit(3);']))->toBe(3);
});

test('run returns -1 when the command times out', function () {
    $runner = new CliRunner;

    expect($runner->run([PHP_BINARY, '-r', 'sleep(5);'], 1))->toBe(-1);
});

test('run returns -1 when the binary cannot be started', function () {
    $runner = new CliRunner;

    expect($runner->run(['definitely-not-a-real-binary-xyz']))->toBe(-1);
});

test('compression levels are configured for light, recommended and strong', function () {
    $levels = config('image_tools.compression.levels');

    expect($levels)->toHaveKeys(['light', 'recommended', 'strong'])
        ->and($levels['recommended']['jpeg_max'])->toBe(85)
        ->and($levels['strong']['png_quality'])->toBe('65-80')
        ->and($levels['light']['jpeg_max'])->toBeNull()
        ->and($levels['light']['png_quality'])->toBeNull()
        ->and(config('image_tools.compression.cli_timeout'))->toBe(120)
        ->and(config('image_tools.avif_speed'))->toBe(8);
});
