<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $user?->normalizedRole() ?? '';
        if (! $user || ! in_array($role, ['super-admin', 'admin'], true)) {
            return $next($request);
        }

        $allowlist = $this->normalizeAllowlist((array) config('security.admin_ip_allowlist', []));
        if ($allowlist === []) {
            return $next($request);
        }

        $ip = (string) $request->ip();
        if (! in_array($ip, $allowlist, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse([
                    'message' => 'Forbidden. Your IP is not allowed for admin access.',
                ], 403);
            }

            abort(403, 'Forbidden. Your IP is not allowed for admin access.');
        }

        return $next($request);
    }

    /**
     * @param  array<int, mixed>  $allowlist
     * @return array<int, string>
     */
    private function normalizeAllowlist(array $allowlist): array
    {
        return array_values(array_filter(array_map(function (mixed $value): string {
            return trim((string) $value);
        }, $allowlist), fn (string $ip): bool => $ip !== ''));
    }
}
