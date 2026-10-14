<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Private channel auth for logged-in CRM users (same checks as the admin area)
        Broadcast::routes(['middleware' => ['web', 'auth', 'active.account', 'single.session']]);

        require base_path('routes/channels.php');
    }
}
