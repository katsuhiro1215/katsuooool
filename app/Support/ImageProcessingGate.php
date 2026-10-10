<?php

namespace App\Support;

use App\Exceptions\Tools\ProcessingBusyException;

class ImageProcessingGate
{
    /**
     * 重い画像処理の同時実行数を制限する。php-fpm が複数の処理を同時に受けても、
     * メモリが重なって app コンテナが落ちないようにする。
     * 枠はファイルロック（flock）で取り、処理が終わるか、プロセスが終了すると自動で解放される。
     */
    public static function run(callable $work): mixed
    {
        $slots = max(1, (int) config('image_tools.concurrency', 2));

        for ($i = 0; $i < $slots; $i++) {
            $handle = fopen(storage_path("framework/image-gate-{$i}.lock"), 'c');

            if ($handle !== false && flock($handle, LOCK_EX | LOCK_NB)) {
                try {
                    return $work();
                } finally {
                    flock($handle, LOCK_UN);
                    fclose($handle);
                }
            }

            if ($handle !== false) {
                fclose($handle);
            }
        }

        throw new ProcessingBusyException('All image processing slots are busy.');
    }
}
