<?php

namespace App\Console\Commands;

use App\Models\UiPatternSnippet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use JsonException;

class ImportUiPatternsCommand extends Command
{
    protected $signature = 'ui-patterns:import
        {category? : 取り込むカテゴリ（省略するとすべて）}
        {--path= : データの置き場所（省略すると database/data/ui-patterns）}
        {--dry-run : 何も書き込まず、追加・更新される内容だけを表示する}';

    protected $description = 'database/data/ui-patterns の JSON から UI パターンを取り込む（同じカテゴリ・タイトルは html/css だけ更新。管理画面での手直しは JSON の内容で上書きされる）';

    public function handle(): int
    {
        $dir = (string) ($this->option('path') ?: database_path('data/ui-patterns'));
        $only = $this->argument('category');

        if (! is_dir($dir)) {
            $this->error("データの置き場所がありません: {$dir}");

            return self::FAILURE;
        }

        $loaded = [];
        $errors = [];

        if ($only !== null && ! in_array($only, UiPatternSnippet::CATEGORIES, true)) {
            $this->error("カテゴリ「{$only}」は許可されていません");

            return self::FAILURE;
        }

        foreach (File::glob($dir.'/*.json') ?: [] as $file) {
            $category = basename($file, '.json');

            if ($only !== null && $only !== $category) {
                continue;
            }

            if (! in_array($category, UiPatternSnippet::CATEGORIES, true)) {
                $errors[] = "{$category}.json: カテゴリ「{$category}」は許可されていません";

                continue;
            }

            try {
                $items = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $errors[] = "{$category}.json: JSON が壊れています（{$e->getMessage()}）";

                continue;
            }

            foreach ($this->validate($category, $items) as $error) {
                $errors[] = $error;
            }

            $loaded[$category] = $items;
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            $this->error('エラーがあるため、何も取り込みませんでした。');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $added = 0;
        $updated = 0;
        $unchanged = 0;
        $willChange = [];

        DB::transaction(function () use ($loaded, $dryRun, &$added, &$updated, &$unchanged, &$willChange): void {
            foreach ($loaded as $category => $items) {
                foreach ($items as $item) {
                    $row = UiPatternSnippet::where('category', $category)->where('title', $item['title'])->first();

                    if ($row === null) {
                        $added++;

                        if (! $dryRun) {
                            UiPatternSnippet::create([
                                'category' => $category,
                                'title' => $item['title'],
                                'html' => $item['html'],
                                'css' => $item['css'],
                            ]);
                        }

                        continue;
                    }

                    if ($row->html === $item['html'] && $row->css === $item['css']) {
                        $unchanged++;

                        continue;
                    }

                    $updated++;
                    $willChange[] = "{$category}: {$item['title']}";

                    if (! $dryRun) {
                        $row->update(['html' => $item['html'], 'css' => $item['css']]);
                    }
                }
            }
        });

        if ($willChange !== []) {
            $this->line($dryRun ? '内容が書き換わる既存の行:' : '内容を書き換えた既存の行:');

            foreach ($willChange as $line) {
                $this->line("  {$line}");
            }
        }

        $suffix = $dryRun ? '（--dry-run のため、何も書き込んでいません）' : '';
        $this->info("追加 {$added} 件・更新 {$updated} 件・変更なし {$unchanged} 件{$suffix}");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function validate(string $category, mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            return ["{$category}.json: 配列ではありません"];
        }

        $errors = [];

        foreach ($items as $index => $item) {
            foreach (['title', 'html', 'css'] as $key) {
                if (! is_array($item) || ! isset($item[$key]) || ! is_string($item[$key]) || trim($item[$key]) === '') {
                    $errors[] = "{$category}.json: {$index} 番目の {$key} がありません";
                }
            }
        }

        return $errors;
    }
}
