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
        $zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $zip->addFile($disk->path($file['path']), $file['download_name']);
        }

        $zip->close();

        return $zipRelativePath;
    }

    public function signedUrl(string $path, string $downloadName, int $expiresInMinutes = 60): string
    {
        return URL::temporarySignedRoute(
            'tools.download',
            now()->addMinutes($expiresInMinutes),
            [
                'token' => base64_encode($path),
                'download_name' => $downloadName,
            ],
        );
    }
}
