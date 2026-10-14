<?php

namespace App\Providers;

use App\Services\CrmOverview;
use App\Support\CompanyContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        Paginator::useBootstrapFive();

        View::share('singleCompany', CompanyContext::single());

        if (CompanyContext::single() && Schema::hasTable('companies')) {
            CompanyContext::default();
        }

        View::composer('admin.dashboard', function ($view) {
            $view->with('crmOverview', app(CrmOverview::class)->snapshot(auth()->user()));
        });
    }
}
