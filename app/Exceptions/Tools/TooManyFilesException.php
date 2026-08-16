<?php

namespace App\Exceptions\Tools;

use RuntimeException;

class TooManyFilesException extends RuntimeException
{
    public static function make(int $count, int $max): self
    {
        return new self("Uploaded {$count} files, which exceeds the maximum of {$max}.");
    }
}
