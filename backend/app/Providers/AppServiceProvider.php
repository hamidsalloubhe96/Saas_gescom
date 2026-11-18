<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\CurrentBranch::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Annuaire central des utilisateurs, tenu à jour automatiquement.
        \App\Models\User::observe(\App\Observers\UserDirectoryObserver::class);

        // Toutes les vues de la console reçoivent l'administrateur connecté.
        View::composer('console.*', function ($view) {
            $view->with('admin', auth('admin')->user());
            // Pastille « paiements à valider » du menu (une seule requête légère par page).
            $view->with('pendingPayments', auth('admin')->check() && \Illuminate\Support\Facades\Schema::hasTable('subscription_payments')
                ? \App\Models\SubscriptionPayment::where('status', 'pending')->count() : 0);
        });

        // Connexion à la console : 5 essais par minute pour un même e-mail depuis une même adresse IP.
        RateLimiter::for('console-login', function (Request $request) {
            $max = (int) config('console.max_login_attempts_per_minute', 5);

            return Limit::perMinute($max)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
