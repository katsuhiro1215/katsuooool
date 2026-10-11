<?php

namespace App\Services\Tools;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * 画像圧縮などで使う外部コマンド（pngquant・jpegoptim など）の実行を包む。
 * テストでは差し替えられるよう、非 final にしている。
 */
class CliRunner
{
    public function has(string $binary): bool
    {
        return (new ExecutableFinder)->find($binary) !== null;
    }

    /**
     * @param  list<string>  $command
     * @return int 終了コード。時間切れや起動失敗は -1
     */
    public function run(array $command, int $timeoutSeconds = 120): int
    {
        // 存在しないコマンドは、起動に失敗したものとして -1 を返す（シェル経由の 127 にしない）
        if (! str_contains($command[0], DIRECTORY_SEPARATOR) && ! $this->has($command[0])) {
            return -1;
        }

        try {
            $process = new Process($command);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            return $process->getExitCode() ?? -1;
        } catch (Throwable) {
            // 時間切れ（ProcessTimedOutException）を含む
            return -1;
        }
    }
}
