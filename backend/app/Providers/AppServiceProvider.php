<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Service\Whatsapp\Contracts\WhatsappGatewayService::class,
            \App\Service\Whatsapp\Implements\GowaGatewayServiceImpl::class
        );

        $this->app->bind(
            \App\Service\Bot\Contracts\ConversationService::class,
            \App\Service\Bot\Implements\ConversationServiceImpl::class
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
