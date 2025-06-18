<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantLoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MerchantAuthController extends Controller
{
    /**
     * Show the merchant login form.
     */
    public function showLoginForm()
    {
        return view('auth.merchant-login');
    }

    /**
     * Handle merchant login request.
     */
    public function login(MerchantLoginRequest $request)
    {
        $this->ensureIsNotRateLimited($request);

        // Attempt authentication with user_type = 'merchant'
        $credentials = array_merge(
            $request->only('email', 'password'),
            ['user_type' => 'merchant']
        );

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            RateLimiter::clear($this->throttleKey($request));

            // Redirect to merchant portal dashboard
            return redirect()->intended('/merchant/dashboard');
        }

        RateLimiter::hit($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    /**
     * Log out the merchant user.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/merchant/login');
    }

    /**
     * Ensure the login request is not rate limited.
     */
    protected function ensureIsNotRateLimited(MerchantLoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    protected function throttleKey(MerchantLoginRequest $request): string
    {
        return 'merchant-login.' . $request->ip();
    }
}
