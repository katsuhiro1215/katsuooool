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

    // AVIF への変換の画素数の上限。メモリは画素数にほぼ比例し、800万画素で約316MB、
    // 1,600万画素以上は 512MB のコンテナでは収まらない（2026-10-10 実測）
    'avif_max_pixels' => 8_000_000,

    // PNG 圧縮で256色化（インデックス化）を試す画素数の上限。
    // 1080p(207万画素)で約8秒、1440p(369万画素)で約15秒、1,200万画素で約130秒、2,500万画素で約265秒かかる
    // （ノイズの多い最悪ケース）。20枚まとめて 300 秒に収めるため、250万画素までに限る
    'png_indexed_max_pixels' => 2_500_000,

    // Imagick のリソース制限（2026-10-10 実測で採用）。超えた分はメモリではなくディスクのキャッシュに逃がす。
    // 25MP の PNG 変換が、制限なしでは強制終了、制限ありでは約23秒・ピーク 81〜97MB で完了した
    'imagick' => [
        'memory' => 128 * 1024 * 1024,
        'map' => 256 * 1024 * 1024,
        'disk' => 2 * 1024 * 1024 * 1024,
        'thread' => 2,
    ],

    // 重い画像処理（変換・圧縮）を同時に走らせる件数
    'concurrency' => 2,

    // AVIF のエンコード速度（0〜10。大きいほど速く、画質はわずかに下がる）。
    // 8 で約3倍速くなる（12MP: 9.3秒→3.3秒）。メモリは変わらない（2026-10-11 実測）
    'avif_speed' => 8,

    // 画像圧縮（M5-2）。CLI（pngquant・oxipng・jpegoptim・cwebp）で行う。
    // jpeg_max: jpegoptim の品質の上限（null は無劣化）／png_quality: pngquant の品質範囲（null は oxipng の無劣化）／webp_quality: cwebp の品質
    'compression' => [
        'default_level' => 'recommended',
        'cli_timeout' => 120,
        // 1リクエスト全体の締め切り（秒）。Caddy の 300秒と fpm の 330秒より手前で切り上げる（M5-2 レビューの実測から）
        'request_budget' => 270,
        // 大きな PNG は pngquant・oxipng のメモリと時間が急に増える（12MP で pngquant 366MB、oxipng 30秒超）。
        // large_pixels を超えたら pngquant は --speed 10、oxipng は -o 1。oxipng_max_pixels を超えて pngquant が品質を満たせないときは、元のまま返す
        'png' => ['large_pixels' => 4_000_000, 'oxipng_max_pixels' => 12_000_000],
        'levels' => [
            'light' => ['jpeg_max' => null, 'png_quality' => null, 'webp_quality' => 90],
            'recommended' => ['jpeg_max' => 85, 'png_quality' => '80-95', 'webp_quality' => 80],
            'strong' => ['jpeg_max' => 75, 'png_quality' => '65-80', 'webp_quality' => 75],
        ],
    ],
];
