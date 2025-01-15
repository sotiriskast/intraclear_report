<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        // Super-admin bypass
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Check role and related permissions
        if ($user->hasRole($role) || $user->hasAllPermissions($this->getRequiredPermissions($role))) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
    private function getRequiredPermissions(string $role): array
    {
        $permissionMap = [
            'admin' => ['manage-users', 'manage-roles'],
            // Add more role-permission mappings
        ];

        return $permissionMap[$role] ?? [];
    }
}
