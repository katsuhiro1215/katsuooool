<?php

namespace Tests\Support;

use App\Services\Tools\CliRunner;

/**
 * CLI の代役。実行されたコマンドを記録し、成功時は出力先のファイルを作る。
 */
class FakeCli extends CliRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /**
     * @param  list<string>  $available  使えることにするコマンド名
     * @param  array<string, int>  $exitCodes  コマンド名ごとの終了コード（既定 0）
     */
    public function __construct(public array $available = [], public array $exitCodes = []) {}

    public function has(string $binary): bool
    {
        return in_array($binary, $this->available, true);
    }

    public function run(array $command, int $timeoutSeconds = 120): int
    {
        $this->commands[] = $command;
        $code = $this->exitCodes[$command[0]] ?? 0;

        if ($code === 0) {
            // -o は cwebp では出力先だが、oxipng では最適化レベル（-o 2）なので、cwebp のときだけ出力先として扱う
            $flags = $command[0] === 'cwebp' ? ['-o'] : ['--output', '--out'];

            foreach ($flags as $flag) {
                $position = array_search($flag, $command, true);

                if ($position !== false && isset($command[$position + 1])) {
                    file_put_contents($command[$position + 1], 'x');
                }
            }
        }

        return $code;
    }
}
