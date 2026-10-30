<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }
    public function boot(): void
    {
        Gate::define('manage-workspace', fn ($user) => $user->hasRole(['owner', 'admin']));
        Gate::define('write-records', fn ($user) => $user->hasRole(['owner', 'admin', 'accountant', 'staff']));
        Gate::define('delete-records', fn ($user) => $user->hasRole(['owner', 'admin']));
        Gate::define('import-records', fn ($user) => $user->hasRole(['owner', 'admin', 'accountant']));
        Paginator::useBootstrapFive();
        Paginator::defaultView('partials.pagination');
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            $view->with('platformAnnouncements', \App\Models\Announcement::visible()->latest('id')->get());
        });
    }
}
