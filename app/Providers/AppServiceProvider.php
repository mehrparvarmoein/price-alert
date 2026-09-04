<?php

namespace App\Providers;

use App\Domain\PriceAlert\Contracts\AlertNotificationSender;
use App\Domain\PriceAlert\Contracts\GoldPriceProvider;
use App\Domain\PriceAlert\Services\MockAlertNotificationSender;
use App\Domain\PriceAlert\Services\MockGoldPriceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            GoldPriceProvider::class,
            MockGoldPriceProvider::class,
        );
        $this->app->bind(
            AlertNotificationSender::class,
            MockAlertNotificationSender::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
