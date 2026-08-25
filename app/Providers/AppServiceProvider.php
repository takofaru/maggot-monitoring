<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
        $this->validateAppUrl();

        // if (str_starts_with((string) config('app.url'), 'https://') || app()->environment('production') || request()->header('X-Forwarded-Proto') === 'https') {
        //     \Illuminate\Support\Facades\URL::forceScheme('https');
        //     request()->server->set('HTTPS', 'on');
        //     request()->server->set('SERVER_PORT', 443);
        // }

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

    private function validateAppUrl(): void
    {
        $appUrl = trim((string) config('app.url'));
        if ($appUrl === '') {
            return;
        }

        $hasMalformedScheme = preg_match('/^[a-z][a-z0-9+\-.]*:\/(?!\/)/i', $appUrl) === 1;
        $hasScheme = parse_url($appUrl, PHP_URL_SCHEME) !== null;
        $hasHost = parse_url($appUrl, PHP_URL_HOST) !== null;
        $isValidAbsoluteUrl = filter_var($appUrl, FILTER_VALIDATE_URL) !== false && $hasScheme && $hasHost;

        if (! $hasMalformedScheme && $isValidAbsoluteUrl) {
            return;
        }

        $message = "Invalid APP_URL configuration detected: '{$appUrl}'. APP_URL must be a valid absolute URL including scheme, e.g. https://your-domain.com.";

        if (app()->isLocal()) {
            Log::warning($message);
            return;
        }

        throw new \RuntimeException($message);
    }
}
