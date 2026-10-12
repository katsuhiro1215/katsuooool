<?php

use App\Models\UiPatternSnippet;
use Illuminate\Support\Facades\File;

function makeDataDir(array $files): string
{
    $dir = sys_get_temp_dir().'/ui-patterns-'.uniqid();
    File::makeDirectory($dir);

    foreach ($files as $name => $content) {
        File::put("$dir/$name", is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE));
    }

    return $dir;
}

function pattern(string $title, string $html = '<p>x</p>', string $css = 'p{color:#333}'): array
{
    return ['title' => $title, 'html' => $html, 'css' => $css];
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/ui-patterns-*') ?: [] as $dir) {
        File::deleteDirectory($dir);
    }
});

test('新しいパターンを追加し、件数を表示する', function () {
    $dir = makeDataDir(['modal.json' => [pattern('シンプルモーダル（Simple Modal）'), pattern('確認モーダル（Confirm Modal）')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])
        ->expectsOutputToContain('追加 2 件・更新 0 件')
        ->assertExitCode(0);

    expect(UiPatternSnippet::where('category', 'modal')->count())->toBe(2)
        ->and(UiPatternSnippet::first()->is_premium)->toBeFalse()
        ->and(UiPatternSnippet::first()->copy_count)->toBe(0);
});

test('2 回実行しても件数は増えず、更新として扱う', function () {
    $dir = makeDataDir(['modal.json' => [pattern('シンプルモーダル（Simple Modal）')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])->assertExitCode(0);
    $this->artisan('ui-patterns:import', ['--path' => $dir])
        ->expectsOutputToContain('追加 0 件・更新 1 件')
        ->assertExitCode(0);

    expect(UiPatternSnippet::count())->toBe(1);
});

test('既存行は html と css だけ更新し、copy_count と is_premium は変えない', function () {
    UiPatternSnippet::create([
        'category' => 'modal', 'title' => 'シンプルモーダル（Simple Modal）',
        'html' => '<p>old</p>', 'css' => 'old{}', 'is_premium' => true, 'copy_count' => 42,
    ]);
    $dir = makeDataDir(['modal.json' => [pattern('シンプルモーダル（Simple Modal）', '<p>new</p>', 'new{}')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])->assertExitCode(0);

    $row = UiPatternSnippet::first();
    expect($row->html)->toBe('<p>new</p>')
        ->and($row->css)->toBe('new{}')
        ->and($row->is_premium)->toBeTrue()
        ->and($row->copy_count)->toBe(42);
});

test('JSON にない既存行（管理画面から登録したもの）には触れない', function () {
    UiPatternSnippet::create(['category' => 'modal', 'title' => '手入力のモーダル', 'html' => '<p>a</p>', 'css' => 'a{}']);
    $dir = makeDataDir(['modal.json' => [pattern('シンプルモーダル（Simple Modal）')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])->assertExitCode(0);

    expect(UiPatternSnippet::where('title', '手入力のモーダル')->exists())->toBeTrue()
        ->and(UiPatternSnippet::count())->toBe(2);
});

test('カテゴリを指定すると、そのカテゴリのファイルだけ取り込む', function () {
    $dir = makeDataDir([
        'modal.json' => [pattern('モーダル（Modal）')],
        'toast.json' => [pattern('トースト（Toast）')],
    ]);

    $this->artisan('ui-patterns:import', ['category' => 'toast', '--path' => $dir])->assertExitCode(0);

    expect(UiPatternSnippet::pluck('category')->all())->toBe(['toast']);
});

test('壊れた JSON・不明なカテゴリ・必須項目の欠落は、何も書き込まずに失敗する', function (array $files, string $message) {
    $dir = makeDataDir($files + ['modal.json' => [pattern('正常なモーダル（Valid Modal）')]]);

    $this->artisan('ui-patterns:import', ['--path' => $dir])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    expect(UiPatternSnippet::count())->toBe(0);
})->with([
    '壊れた JSON' => [['toast.json' => '{broken'], 'JSON'],
    '不明なカテゴリ' => [['nothing.json' => [pattern('x（X）')]], 'カテゴリ'],
    'html がない' => [['toast.json' => [['title' => 'a（A）', 'css' => 'a{}']]], 'html'],
]);
