<?php

namespace App\Providers;

use App\Models\Quote;
use App\Models\User;
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
     *
     * Roles are intentionally kept as three flat abilities rather than a
     * full permissions matrix — matches the RBAC scope in the planning doc
     * (§17), which only calls out Admin/Sales/Manager, not per-action
     * permissions.
     */
    public function boot(): void
    {
        // Admin only: product CSV import, pricing rules.
        Gate::define('manage-catalog', fn (User $user) => $user->isAdmin());

        // Admin only: change another user's role.
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());

        // Admin + Manager: see every quote in the list/history, not just
        // their own.
        Gate::define('view-all-quotes', fn (User $user) => $user->canViewAllQuotes());

        // A specific quote is viewable by whoever created it, plus anyone
        // who can see all quotes (Admin/Manager).
        Gate::define('view-quote', fn (User $user, Quote $quote) => $user->canViewAllQuotes() || $quote->user_id === $user->id);

        // Admin only — Manager can see every quote but is explicitly
        // read-only (see canViewAllQuotes()'s docblock), and Sales never
        // gets destructive access even to their own quotes.
        Gate::define('delete-quotes', fn (User $user) => $user->isAdmin());
    }
}
