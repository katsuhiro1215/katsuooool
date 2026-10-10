<?php

namespace App\Http\Middleware;

use App\Exceptions\Tools\ProcessingBusyException;
use App\Support\ImageProcessingGate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LimitImageProcessing
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return ImageProcessingGate::run(fn () => $next($request));
        } catch (ProcessingBusyException) {
            // 枠が足りているかを運用で判断できるように記録する
            Log::info('Image processing slots are busy; responded 503.', ['path' => $request->path()]);

            return response()->json([
                'message' => '現在アクセスが集中しています。少し待ってから、もう一度お試しください。',
            ], 503, ['Retry-After' => '10']);
        }
    }
}
