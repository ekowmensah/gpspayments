<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('admin-panel', function ($user): bool {
            return $user->hasRole('Administrator', 'Treasurer', 'Secretary', 'Auditor');
        });

        Gate::define('member-portal', function ($user): bool {
            return !empty($user->member_id);
        });
    }
}
