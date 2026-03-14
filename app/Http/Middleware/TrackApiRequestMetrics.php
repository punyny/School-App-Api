<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackApiRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Response-Time-Ms', (string) $durationMs);

        $slowThresholdMs = (int) config('security.monitoring.slow_api_ms', 1200);
        if ($request->is('api/*') && $durationMs >= $slowThresholdMs) {
            Log::warning('Slow API request detected', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
        }

        return $response;
    }
}
