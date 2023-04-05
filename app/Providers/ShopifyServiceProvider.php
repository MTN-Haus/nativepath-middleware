<?php

namespace App\Providers;

use App\Services\Shopify\ShopifyService;
use Illuminate\Support\ServiceProvider;
use Shopify\Context;
use Shopify\Auth\FileSessionStorage;

/**
 * ShopifyServiceProvider class
 *
 * @package App\Providers
 */
class ShopifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Context::initialize(
            env('SHOPIFY_API_KEY'),
            env('SHOPIFY_API_TOKEN'),
            env('SHOPIFY_API_SCOPES'),
            env('APP_URL'),
            new FileSessionStorage('/tmp/shopify_sessions'),
            env('SHOPIFY_API_VERSION'),
            false,
            true
        );

        $this->app->singleton(ShopifyService::class, function ($app) {
            return new ShopifyService();
        });
    }
}
