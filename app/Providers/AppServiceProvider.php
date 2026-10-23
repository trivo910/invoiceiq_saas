<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Support\TenantContext;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Paginator::defaultView('partials.pagination');
    }
}
