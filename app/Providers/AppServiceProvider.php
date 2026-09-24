<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // See config/database.php `read_timeout`: pdo_mysql has no per-connection read
        // timeout, so the mysqlnd-wide ini is the only way to bound a stalled handshake.
        $connection = config('database.default');
        if (in_array($connection, ['mysql', 'mariadb'], true)) {
            ini_set('mysqlnd.net_read_timeout', (string) config("database.connections.{$connection}.read_timeout", 10));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('username').'|'.$request->ip());
        });

        // Keyed by email+IP so one spammed address can't be used to lock out a shared IP
        // (e.g. an office NAT) from requesting resets for other accounts.
        RateLimiter::for('password-reset-request', function (Request $request) {
            return Limit::perMinute(3)->by($request->input('email').'|'.$request->ip());
        });

        // Confirm/validate steps carry the token itself, so IP alone is enough to slow
        // down brute-forcing a guessed token.
        RateLimiter::for('password-reset-confirm', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
