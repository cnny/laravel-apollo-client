<?php

namespace Cann\Apollo;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Cann\Apollo\Commands\ClientStart;

class ApolloServiceProvider extends BaseServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ClientStart::class,
            ]);
        }
    }

    public function register()
    {
        // do nothing
    }
}
