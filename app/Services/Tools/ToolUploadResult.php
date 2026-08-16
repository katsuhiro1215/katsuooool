<?php

namespace App\Services\Tools;

final readonly class ToolUploadResult
{
    /**
     * @param  array<int, UploadedToolFile>  $files
     * @param  array<int, string>  $rejected
     */
    public function __construct(
        public string $batchId,
        public array $files,
        public array $rejected,
    ) {}
}
