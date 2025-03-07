<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DashboardApiAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated using the web guard
        if (!Auth::guard('web')->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Authentication required',
                ], 401);
            }

            // Redirect to login if not an API request
            return redirect()->route('login');
        }

        // Add a custom header to identify dashboard API requests
        $request->headers->set('X-Dashboard-API', true);

        return $next($request);
    }
}
