<?php

namespace App\Services\Tools;

use App\Exceptions\Tools\TooManyFilesException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ToolUploadService
{
    /**
     * @param  array<int, UploadedFile>  $uploadedFiles
     * @param  array<int, string>  $allowedMimeTypes
     */
    public function store(
        array $uploadedFiles,
        array $allowedMimeTypes,
        int $maxFileBytes,
        int $maxFileCount,
    ): ToolUploadResult {
        if (count($uploadedFiles) > $maxFileCount) {
            throw TooManyFilesException::make(count($uploadedFiles), $maxFileCount);
        }

        $batchId = (string) Str::uuid();
        $files = [];
        $rejected = [];

        foreach ($uploadedFiles as $index => $uploadedFile) {
            if ($uploadedFile->getSize() > $maxFileBytes) {
                $rejected[$index] = 'ファイルサイズが上限を超えています。';

                continue;
            }

            $mimeType = $uploadedFile->getMimeType();

            if ($mimeType === null || ! in_array($mimeType, $allowedMimeTypes, true)) {
                $rejected[$index] = '対応していないファイル形式です。';

                continue;
            }

            $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension();
            $storedName = Str::uuid().'.'.$extension;
            $path = "tool-tmp/{$batchId}/{$storedName}";

            Storage::disk('local')->putFileAs(
                "tool-tmp/{$batchId}",
                $uploadedFile,
                $storedName,
            );

            $files[] = new UploadedToolFile(
                path: $path,
                originalName: $uploadedFile->getClientOriginalName(),
                size: $uploadedFile->getSize(),
                mimeType: $mimeType,
            );
        }

        return new ToolUploadResult($batchId, $files, $rejected);
    }
}
