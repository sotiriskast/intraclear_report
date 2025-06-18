<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class EnsureMerchantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect('/merchant/login');
        }

        // CRITICAL: Check if user account is active FIRST
        if (!$user->active) {
            Log::warning('Inactive merchant user attempted access', [
                'user_id' => $user->id,
                'email' => $user->email,
                'merchant_id' => $user->merchant_id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            auth()->logout();

            return redirect('/merchant/login')->with('error', 'Your merchant account has been deactivated. Please contact support.');
        }

        if ($user->user_type !== 'merchant') {
            Log::warning('Non-merchant user attempted merchant access', [
                'user_id' => $user->id,
                'user_type' => $user->user_type,
                'ip' => $request->ip()
            ]);

            abort(403, 'Merchant access required');
        }

        // Ensure merchant exists and is active
        if (!$user->merchant || !$user->merchant->active) {
            Log::warning('Merchant user with inactive merchant attempted access', [
                'user_id' => $user->id,
                'merchant_id' => $user->merchant_id,
                'merchant_active' => $user->merchant?->active,
                'ip' => $request->ip()
            ]);

            abort(403, 'Access denied. Merchant account is inactive.');
        }

        // Ensure merchant can only access their own data
        if ($merchantId = $request->route('merchant')) {
            if ($user->merchant_id !== $merchantId) {
                Log::warning('Merchant user attempted to access other merchant data', [
                    'user_id' => $user->id,
                    'user_merchant_id' => $user->merchant_id,
                    'attempted_merchant_id' => $merchantId,
                    'ip' => $request->ip()
                ]);

                abort(403, 'Access to this merchant data is forbidden');
            }
        }

        return $next($request);
    }
}
