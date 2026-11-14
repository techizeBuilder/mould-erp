<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
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
        // @module('sales', 'create') ... @endmodule
        Blade::if('module', fn (string $module, string $action = 'view') => (bool) auth()->user()?->canModule($module, $action));

        Blade::if('superadmin', fn () => (bool) auth()->user()?->isSuperAdmin());

        Paginator::useTailwind();

        // WAMP's MySQL may default to MyISAM with a 1000-byte index limit.
        Schema::defaultStringLength(191);
    }
}
