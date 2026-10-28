<?php

namespace App\Providers;

use App\Services\CrmOverview;
use App\Support\CompanyContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
        Paginator::useBootstrapFive();

        // Config-only — safe even when MySQL is saturated
        View::share('singleCompany', CompanyContext::single());

        try {
            if (CompanyContext::single() && Schema::hasTable('companies')) {
                CompanyContext::default();
            }
        } catch (Throwable $e) {
            // Don't take down auth/logout (and every request) when MySQL hits max_connections
            Log::warning('AppServiceProvider company bootstrap skipped: '.$e->getMessage());
        }

        View::composer('admin.dashboard', function ($view) {
            try {
                $view->with('crmOverview', app(CrmOverview::class)->snapshot(auth()->user()));
            } catch (Throwable $e) {
                Log::warning('CrmOverview snapshot failed: '.$e->getMessage());
                $view->with('crmOverview', null);
            }
        });
    }
}
