<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', (string) config('security.headers.x_frame_options', 'SAMEORIGIN'));
        $headers->set('X-Content-Type-Options', (string) config('security.headers.x_content_type_options', 'nosniff'));
        $headers->set('Referrer-Policy', (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $headers->set('Permissions-Policy', (string) config('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=()'));

        if ($request->isSecure()) {
            $headers->set(
                'Strict-Transport-Security',
                (string) config('security.headers.strict_transport_security', 'max-age=31536000; includeSubDomains')
            );
        }

        $contentSecurityPolicy = trim((string) config('security.headers.content_security_policy', ''));
        if ($contentSecurityPolicy !== '') {
            $headers->set('Content-Security-Policy', $contentSecurityPolicy);
        }

        return $response;
    }
}
