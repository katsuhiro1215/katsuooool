<?php

use App\Models\UiPatternSnippet;
use Illuminate\Support\Facades\File;

/** カテゴリごとの最低件数（設計書 §4.4）。ファイルがあるカテゴリだけ検査する。 */
const UI_PATTERN_MIN_COUNTS = [
    'modal' => 10, 'toast' => 10, 'loading' => 10, 'skeleton' => 8, 'header' => 8, 'footer' => 8,
    'form-input' => 10, 'form-textarea' => 6, 'form-check' => 10, 'form-select' => 8, 'form-group' => 8,
    'pagination' => 7,
];

function uiPatternFiles(): array
{
    return File::glob(database_path('data/ui-patterns').'/*.json') ?: [];
}

test('データのファイル名はカテゴリの許可リストにある', function () {
    foreach (uiPatternFiles() as $file) {
        expect(UiPatternSnippet::CATEGORIES)->toContain(basename($file, '.json'));
    }
});

test('全パターンが必須項目・タイトル形式・決まりを満たす', function () {
    foreach (uiPatternFiles() as $file) {
        $category = basename($file, '.json');
        $items = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
        $titles = [];

        foreach ($items as $i => $item) {
            $where = "{$category}.json #{$i} ".($item['title'] ?? '');

            foreach (['title', 'html', 'css'] as $key) {
                expect($item[$key] ?? null, "$where: $key")->toBeString()->not->toBeEmpty();
            }

            expect(mb_strlen($item['title']), "$where: タイトルの長さ")->toBeLessThanOrEqual(255);
            expect($item['title'], "$where: 「日本語名（English Name）」の形式")->toMatch('/^.+（[A-Za-z0-9][A-Za-z0-9 \-\/&\.\+\']*）$/u');
            expect($titles, "$where: タイトルの重複")->not->toContain($item['title']);
            $titles[] = $item['title'];

            $code = $item['html']."\n".$item['css'];
            expect($code, "$where: script")->not->toMatch('/<\s*script/i');
            expect($code, "$where: position: fixed")->not->toMatch('/position\s*:\s*fixed/i');
            expect($code, "$where: vh/vw")->not->toMatch('/\d\s*(vh|vw|dvh|svh)\b/i');
            expect($code, "$where: @import")->not->toMatch('/@import/i');

            preg_match_all('#https?://[^\s"\'()<>]+#i', $code, $urls);
            foreach ($urls[0] as $url) {
                expect($url, "$where: 外部 URL")->toStartWith('https://placehold.co/');
            }
        }
    }
});

test('各カテゴリが最低件数を満たす', function () {
    foreach (uiPatternFiles() as $file) {
        $category = basename($file, '.json');
        $items = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);

        expect(count($items), "{$category}.json の件数")->toBeGreaterThanOrEqual(UI_PATTERN_MIN_COUNTS[$category] ?? 1);
    }
});

test('別のパターンとクラス名を共有しない（コピーして同じページに並べても衝突しない）', function () {
    $owners = [];

    foreach (uiPatternFiles() as $file) {
        $category = basename($file, '.json');

        foreach (json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR) as $item) {
            preg_match_all('/\.([A-Za-z_][\w-]*)/', preg_replace('/\d+\.\d+/', '', $item['css']), $m);

            foreach (array_unique($m[1]) as $class) {
                // 状態を表す `is-*`（is-current など）は、パターンの外から付け外しする前提の名前なので共有してよい。
                if (str_starts_with($class, 'is-')) {
                    continue;
                }

                $owners[$class][$category.'/'.$item['title']] = true;
            }
        }
    }

    $shared = array_filter($owners, fn ($patterns) => count($patterns) > 1);

    expect(array_map(fn ($patterns) => array_keys($patterns), $shared))->toBe([]);
});

test('固定幅が 500px 以上の規則は、狭い画面で横にはみ出さないよう max-width: 100% を持つ', function () {
    foreach (uiPatternFiles() as $file) {
        $category = basename($file, '.json');

        foreach (json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR) as $item) {
            foreach (explode('}', $item['css']) as $rule) {
                if (preg_match('/(?<![-\w])width\s*:\s*(\d{3,})px/', $rule, $m) && (int) $m[1] >= 500) {
                    expect($rule, "{$category}/{$item['title']}: 幅 {$m[1]}px")->toMatch('/max-width\s*:\s*100%/');
                }
            }
        }
    }
});
