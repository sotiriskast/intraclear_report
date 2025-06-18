<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect('/login');
        }

        // CRITICAL: Block merchant users from accessing admin portal
        if ($user->user_type === 'merchant') {
            // Log the attempt for security monitoring
            \Log::warning('Merchant user attempted to access admin portal', [
                'user_id' => $user->id,
                'email' => $user->email,
                'merchant_id' => $user->merchant_id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempted_url' => $request->fullUrl()
            ]);

            // Redirect to merchant portal instead
            return redirect('/merchant/dashboard')
                ->with('error', 'Access denied. You have been redirected to your merchant portal.');
        }

        // Only allow admin and super-admin user types
        if (!in_array($user->user_type, ['admin', 'super-admin'])) {
            abort(403, 'Admin access required');
        }

        return $next($request);
    }
}
