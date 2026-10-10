<?php

// 画像ツールの上限。値の根拠は docs/superpowers/specs/2026-10-10-m5-1-limits-design.md
return [
    // 一度にアップロードできる枚数
    'max_files' => ['free' => 10, 'premium' => 20],

    // AVIF への変換は負荷が高い（1枚あたり 5〜20 秒）ため、枚数を絞る
    'avif_max_files' => ['free' => 2, 'premium' => 5],

    // 1ファイルのサイズ（バイト）
    'max_file_bytes' => 10 * 1024 * 1024,

    // 画素数の上限
    'max_pixels' => 25_000_000,

    // Imagick のリソース制限（Task 6 の実測で調整する。null は設定しない）
    'imagick' => [
        'memory' => null,
        'map' => null,
        'disk' => null,
        'thread' => null,
    ],
];
