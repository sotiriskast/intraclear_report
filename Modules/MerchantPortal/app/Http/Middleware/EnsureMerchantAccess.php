<?php

namespace Modules\MerchantPortal\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMerchantAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Ensure user is authenticated and is a merchant
        if (!$user || $user->user_type !== 'merchant') {
            abort(403, 'Merchant access required');
        }

        // Ensure user has a merchant_id
        if (!$user->merchant_id) {
            abort(403, 'No merchant associated with this account');
        }

        // If route has merchant parameter, ensure it matches user's merchant
        if ($merchantId = $request->route('merchant')) {
            if ($user->merchant_id !== (int)$merchantId) {
                abort(403, 'Access to this merchant data is forbidden');
            }
        }

        return $next($request);
    }
}
