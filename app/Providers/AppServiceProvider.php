<?php

namespace App\Providers;

use App\Models\Billing;
use App\Models\CollectorReminder;
use App\Models\CollectorVisit;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Observers\BillingCollectionObserver;
use App\Observers\CollectionTimelineObserver;
use App\Services\CollectionAccountRefreshQueue;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CollectionAccountRefreshQueue::class);

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
        $this->bootCollectionObservers();

        // Per-account failed-attempt lockout lives in AuthController::login; this is only an
        // IP-wide backstop, loose enough that successful logins/re-logins never hit it.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip())->response(fn () => response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan login. Silakan coba lagi beberapa saat lagi.',
            ], 429));
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

    /**
     * Timeline & cache status akun penagihan mengikuti perubahan entitas sumber dari endpoint
     * mana pun. Refresh tagihan yang tertunda di-flush di akhir request/command dan setelah
     * setiap job (worker antrean berumur panjang tidak pernah "terminating").
     */
    private function bootCollectionObservers(): void
    {
        CollectorVisit::observe(CollectionTimelineObserver::class);
        PaymentPromise::observe(CollectionTimelineObserver::class);
        PaymentTransaction::observe(CollectionTimelineObserver::class);
        CollectorReminder::observe(CollectionTimelineObserver::class);
        Billing::observe(BillingCollectionObserver::class);

        $flush = function () {
            try {
                $this->app->make(CollectionAccountRefreshQueue::class)->flush();
            } catch (Throwable $e) {
                report($e);
            }
        };

        $this->app->terminating($flush);
        Queue::after($flush);
    }
}
