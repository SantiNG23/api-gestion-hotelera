<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn (): TenantContext => new TenantContext);
        $this->app->scoped(TenantContextResolver::class, fn (): TenantContextResolver => new TenantContextResolver);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
