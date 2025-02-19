<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

class ApiAuthenticationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) {
            return response()->json([
                'error' => true,
                'message' => 'No token provided.',
                'status_code' => 401
            ], 401);
        }

        if (!auth()->guard('sanctum')->check()) {
            return response()->json([
                'error' => true,
                'message' => 'Invalid token.',
                'status_code' => 401
            ], 401);
        }

        return $next($request);
    }
}
