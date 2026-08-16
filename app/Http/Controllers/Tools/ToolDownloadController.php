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
        $path = $this->decodeToken($token);

        abort_if($path === false, 404);

        $normalized = str_replace('\\', '/', $path);

        abort_unless(str_starts_with($normalized, 'tool-tmp/'), 404);
        abort_if(str_contains($normalized, '..'), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($normalized), 404);

        $downloadName = $request->query('download_name', basename($normalized));

        return $disk->download($normalized, $downloadName);
    }

    /**
     * Decode a base64url-encoded token back into its original path.
     */
    private function decodeToken(string $token): string|false
    {
        $base64 = strtr($token, '-_', '+/');
        $padded = str_pad(
            $base64,
            strlen($base64) % 4 === 0 ? strlen($base64) : strlen($base64) + (4 - strlen($base64) % 4),
            '=',
            STR_PAD_RIGHT,
        );

        return base64_decode($padded, strict: true);
    }
}
