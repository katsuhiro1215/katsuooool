<?php

namespace App\Services\Tools;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ZipArchive;

class ToolDownloadService
{
    /**
     * @param  array<int, array{path: string, download_name: string}>  $files
     */
    public function zip(string $batchId, array $files, string $zipFileName): string
    {
        $disk = Storage::disk('local');
        $zipRelativePath = "tool-tmp/{$batchId}/{$zipFileName}";
        $absoluteZipPath = $disk->path($zipRelativePath);

        $zip = new ZipArchive;
        $opened = $zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new \RuntimeException("Failed to open zip archive at {$absoluteZipPath} (error code {$opened}).");
        }

        foreach ($files as $file) {
            if (! $zip->addFile($disk->path($file['path']), $file['download_name'])) {
                throw new \RuntimeException("Failed to add file {$file['path']} to zip archive.");
            }
        }

        if (! $zip->close()) {
            throw new \RuntimeException("Failed to finalize zip archive at {$absoluteZipPath}.");
        }

        return $zipRelativePath;
    }

    public function signedUrl(string $path, string $downloadName, int $expiresInMinutes = 60): string
    {
        return URL::temporarySignedRoute(
            'tools.download',
            now()->addMinutes($expiresInMinutes),
            [
                'token' => rtrim(strtr(base64_encode($path), '+/', '-_'), '='),
                'download_name' => $downloadName,
            ],
        );
    }
}
