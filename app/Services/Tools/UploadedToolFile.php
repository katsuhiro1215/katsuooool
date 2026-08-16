<?php

namespace App\Services\Tools;

final readonly class UploadedToolFile
{
    public function __construct(
        public string $path,
        public string $originalName,
        public int $size,
        public string $mimeType,
    ) {}
}
