<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

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
        Gate::define('has-account', function (User $user) {
            return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_USER]);
        });

        Gate::define('manage-accounts', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('view-account', function (User $currentUser, ?User $targetUser = null) {
            if ($currentUser->isAdmin()) {
                return true;
            }
            return $targetUser === null || $currentUser->id === $targetUser->id;
        });
    }
}
