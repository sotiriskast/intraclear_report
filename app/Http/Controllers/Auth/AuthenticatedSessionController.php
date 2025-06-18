<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Get the authenticated user
        $user = Auth::user();

        // CRITICAL: Redirect based on user type
        if ($user->user_type === 'merchant') {
            // Log merchant login attempt to admin portal
            \Log::info('Merchant user logged in via admin portal - redirecting to merchant portal', [
                'user_id' => $user->id,
                'email' => $user->email,
                'merchant_id' => $user->merchant_id,
                'ip' => $request->ip()
            ]);

            // Redirect merchants to their portal
            return redirect('/merchant/dashboard')
                ->with('success', 'Welcome! You have been redirected to your merchant portal.');
        }

        // For admin/super-admin users, redirect to admin dashboard
        if (in_array($user->user_type, ['admin', 'super-admin'])) {
            return redirect()->intended('/admin/dashboard');
        }

        // Fallback - should not happen with proper seeding
        Auth::logout();
        return redirect('/login')->with('error', 'Invalid user type. Please contact support.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
