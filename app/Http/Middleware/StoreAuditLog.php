<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class StoreAuditLog
{
    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'authorization',
        'remember_token',
        'password_hash',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->storeLog($request, $response);

        return $response;
    }

    private function storeLog(Request $request, Response $response): void
    {
        if (! $request->is('api/*')) {
            return;
        }

        if ($request->is('api/auth/*')) {
            return;
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $user = $request->user();
        $role = $user?->normalizedRole() ?? '';
        if (! $user || ! in_array($role, ['super-admin', 'admin'], true)) {
            return;
        }

        $path = $request->path();
        $segments = $request->segments();
        $resourceType = $segments[1] ?? null;
        $resourceId = null;

        foreach (array_slice($segments, 2) as $segment) {
            if (ctype_digit((string) $segment)) {
                $resourceId = (int) $segment;
                break;
            }
        }

        try {
            AuditLog::query()->create([
                'actor_id' => (int) $user->id,
                'school_id' => $this->resolveSchoolId($request),
                'actor_name' => (string) $user->name,
                'actor_role' => $role,
                'method' => $request->method(),
                'endpoint' => '/'.$path,
                'action' => strtoupper($request->method()).' /'.$path,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_payload' => $this->sanitizePayload($request->all()),
                'status_code' => $response->getStatusCode(),
            ]);
        } catch (\Throwable) {
            // Never block actual feature request flow due to audit log failures.
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array(Str::lower((string) $key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '***';

                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    /**
     * @return mixed
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return Str::limit($value, 500, '...');
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(Str::lower($key), self::SENSITIVE_KEYS, true)) {
                    $clean[$key] = '***';

                    continue;
                }
                $clean[$key] = $this->sanitizeValue($item);
            }

            return $clean;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return Str::limit((string) $value, 500, '...');
        }

        return null;
    }

    private function resolveSchoolId(Request $request): ?int
    {
        $user = $request->user();
        if ($user?->school_id) {
            return (int) $user->school_id;
        }

        $payloadSchoolId = (int) $request->input('school_id', 0);
        if ($payloadSchoolId > 0) {
            return $payloadSchoolId;
        }

        return null;
    }
}
