<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
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
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url')
                . '/reset-password?token=' . $token
                . '&email=' . urlencode($user->getEmailForPasswordReset());
        });

        VerifyEmail::createUrlUsing(function ($user) {
            $hash = sha1($user->getEmailForVerification());

            $signed = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'id'   => $user->getKey(),
                'hash' => $hash,
            ]);

            return config('app.frontend_url')
                . "/verify-email/{$user->getKey()}/{$hash}?" . parse_url($signed, PHP_URL_QUERY);
        });

        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', function (Request $request) {
            $username = Str::lower((string) $request->input('username'));

            return [
                Limit::perMinute(5)->by('login-akun-ip|'.$username.'|'.$request->ip()),   // tebakan satu akun dari satu IP
                Limit::perMinute(60)->by('login-ip|'.$request->ip()),                      // total per IP, longgar
                Limit::perHour(30)->by('login-akun|'.$username),                           // satu akun dari mana saja
            ];
        });

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(10)->by('reg-menit|'.$request->ip()),
            Limit::perHour(60)->by('reg-jam|'.$request->ip()),
        ]);

        RateLimiter::for('forgot-password', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(3)->by('fp-email-ip|'.$email.'|'.$request->ip()),
                Limit::perHour(5)->by('fp-email|'.$email),             // satu email tidak bisa dibanjiri
                Limit::perHour(30)->by('fp-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('reset-password', fn (Request $request) =>
            Limit::perMinute(5)->by('rp|'.Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('verify', fn (Request $request) =>
            Limit::perMinute(10)->by('verify|'.$request->ip()));

        RateLimiter::for('verification', fn (Request $request) =>
            Limit::perMinute(3)->by('verif|'.($request->user()?->id ?: $request->ip())));

        RateLimiter::for('password-change', fn (Request $request) =>
            Limit::perMinute(5)->by('pwd|'.($request->user()?->id ?: $request->ip())));
    }
}
