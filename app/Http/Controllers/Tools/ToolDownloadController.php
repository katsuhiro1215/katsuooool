<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ToolDownloadController extends Controller
{
    public function show(Request $request, string $token): StreamedResponse
    {
        $path = base64_decode($token, strict: true);

        abort_if($path === false, 404);

        $normalized = str_replace('\\', '/', $path);

        abort_unless(str_starts_with($normalized, 'tool-tmp/'), 404);
        abort_if(str_contains($normalized, '..'), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($normalized), 404);

        $downloadName = $request->query('download_name', basename($normalized));

        return $disk->download($normalized, $downloadName);
    }
}
