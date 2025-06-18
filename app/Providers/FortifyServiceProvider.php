<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //Fortify::registerView(fn () => abort(404));
        //Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                // Log all login attempts for security
                \Log::info('User login attempt', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'ip' => $request->ip(),
                    'route' => $request->route()?->getName()
                ]);

                // For merchant users, redirect directly to merchant dashboard
                if ($user->user_type === 'merchant') {
                    // Store a value in the session to indicate this is a merchant login
                    // This will be used by the 2FA middleware to bypass 2FA for merchants
                    session(['is_merchant_user' => true]);

                    // After successful login, redirect to merchant dashboard
                    redirect('/merchant/dashboard')
                        ->with('success', 'Welcome! You have been redirected to your merchant portal.');
                }

                // Allow authentication for all user types
                return $user;
            }

            return null;
        });
    }
}
