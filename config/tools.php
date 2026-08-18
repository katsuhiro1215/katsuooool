<?php

return [
    [
        'slug' => 'image-compress',
        'name' => '画像圧縮',
        'category' => 'image',
        'description' => 'JPEG・PNG・WebP画像を自動で最適化し、軽量化します。',
        'href' => '/image-compress',
    ],
    [
        'slug' => 'image-convert',
        'name' => '画像形式変換',
        'category' => 'image',
        'description' => 'JPEG・PNG・WebP・AVIF間で画像形式を一括変換します。',
        'href' => '/image-convert',
    ],
    [
        'slug' => 'image-resize',
        'name' => '画像リサイズ',
        'category' => 'image',
        'description' => '幅・高さを指定して画像サイズを変更します。アスペクト比の自動維持にも対応。',
        'href' => '/image-resize',
    ],
    [
        'slug' => 'image-color-extract',
        'name' => '画像から色を抽出',
        'category' => 'image',
        'description' => '画像から主要な色を6色抽出し、HEXコードで表示します。',
        'href' => '/image-color-extract',
    ],
    [
        'slug' => 'image-rotate',
        'name' => '画像の回転',
        'category' => 'image',
        'description' => '画像を左90度・180度・右90度のいずれかに一括回転します。',
        'href' => '/image-rotate',
    ],
    [
        'slug' => 'color-convert',
        'name' => 'HEX/RGB/HSL変換',
        'category' => 'color',
        'description' => 'HEX・RGB・HSLの色表記をリアルタイムに相互変換します。',
        'href' => '/color-convert',
    ],
    [
        'slug' => 'color-contrast',
        'name' => 'コントラストチェック',
        'category' => 'color',
        'description' => '文字色と背景色のWCAGコントラスト比を計算し、AA/AAA適合を判定します。',
        'href' => '/color-contrast',
    ],
    [
        'slug' => 'color-palette',
        'name' => 'カラーパレット生成',
        'category' => 'color',
        'description' => 'ランダムな5色のカラーパレットを生成します。色ごとにロックして再生成できます。',
        'href' => '/color-palette',
    ],
    [
        'slug' => 'color-gradient',
        'name' => 'グラデーション生成',
        'category' => 'color',
        'description' => '2色と角度からCSSの線形グラデーションを生成します。',
        'href' => '/color-gradient',
    ],
    [
        'slug' => 'color-scheme',
        'name' => '色の組み合わせ',
        'category' => 'color',
        'description' => '基準色から補色・類似色・三色配色などの組み合わせを提案します。',
        'href' => '/color-scheme',
    ],
];
