<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class EnsureAdminAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect('/login')->with('error', 'Please login to continue.');
        }

        // CRITICAL: Check if user account is active FIRST
        if (!$user->active) {
            Log::warning('Inactive user attempted admin access', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            auth()->logout();

            return redirect('/login')->with('error', 'Your account has been deactivated. Please contact support.');
        }

        // CRITICAL: Block merchant users from accessing admin portal
        if ($user->user_type === 'merchant') {
            // Rate limit malicious attempts
            $key = 'merchant_admin_attempt:' . $user->id;
            if (RateLimiter::tooManyAttempts($key, 3)) {
                Log::critical('Merchant user making repeated admin access attempts', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'merchant_id' => $user->merchant_id,
                    'ip' => $request->ip(),
                    'attempts' => RateLimiter::attempts($key)
                ]);

                abort(429, 'Too many unauthorized access attempts. Account flagged for review.');
            }

            RateLimiter::hit($key, 3600); // 1 hour window

            // Log the attempt for security monitoring
            Log::warning('Merchant user attempted to access admin portal', [
                'user_id' => $user->id,
                'email' => $user->email,
                'merchant_id' => $user->merchant_id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempted_url' => $request->fullUrl(),
                'referer' => $request->header('referer')
            ]);

            // Redirect to merchant portal instead of showing error
            return redirect('/merchant/dashboard')
                ->with('error', 'Access denied. You have been redirected to your merchant portal.');
        }

        // Only allow admin and super-admin user types
        if (!in_array($user->user_type, ['admin', 'super-admin'])) {
            Log::warning('Invalid user type attempted admin access', [
                'user_id' => $user->id,
                'user_type' => $user->user_type,
                'ip' => $request->ip()
            ]);

            abort(403, 'Admin access required');
        }

        return $next($request);
    }
}
