<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => $this->normalizeRoleName($role))
            ->filter()
            ->values()
            ->all();

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->away(route('login', [], false));
        }

        $userRole = $this->resolveUserRole($user);
        if ($allowedRoles !== [] && ! in_array($userRole, $allowedRoles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Forbidden for this role.'], 403);
            }

            abort(403, 'Forbidden for this role.');
        }

        return $next($request);
    }

    private function resolveUserRole(mixed $user): string
    {
        $rawRole = $user->role ?? null;
        $normalized = $this->normalizeRoleName(is_string($rawRole) ? $rawRole : '');
        if ($normalized !== '') {
            return $normalized;
        }

        $roleName = $user->roleRef?->role_name ?? null;

        return $this->normalizeRoleName(is_string($roleName) ? $roleName : '');
    }

    private function normalizeRoleName(string $role): string
    {
        $value = trim(strtolower($role));

        return match ($value) {
            'super admin', 'super_admin', 'super-admin' => 'super-admin',
            'guardian', 'parent' => 'parent',
            'admin' => 'admin',
            'teacher' => 'teacher',
            'student' => 'student',
            default => $value,
        };
    }
}
