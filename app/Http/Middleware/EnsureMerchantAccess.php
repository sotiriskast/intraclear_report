<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        if ($user->user_type !== 'merchant') {
            abort(403, 'Merchant access required');
        }

        // Ensure merchant exists and is active
        if (!$user->merchant || !$user->merchant->active) {
            abort(403, 'Access denied. Merchant account is inactive.');
        }

        // Ensure merchant can only access their own data
        if ($merchantId = $request->route('merchant')) {
            if ($user->merchant_id !== $merchantId) {
                abort(403, 'Access to this merchant data is forbidden');
            }
        }

        return $next($request);
    }
}
