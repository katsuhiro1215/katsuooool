<?php

namespace App\Http\Middleware;

use App\Exceptions\Tools\ProcessingBusyException;
use App\Support\ImageProcessingGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitImageProcessing
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return ImageProcessingGate::run(fn () => $next($request));
        } catch (ProcessingBusyException) {
            return response()->json([
                'message' => '現在アクセスが集中しています。少し待ってから、もう一度お試しください。',
            ], 503);
        }
    }
}
