<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMerchantIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive or suspended'
            ], 403);
        }

        return $next($request);
    }
}
