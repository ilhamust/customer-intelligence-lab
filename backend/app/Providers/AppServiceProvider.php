<?php

namespace App\Providers;

use App\Service\Bot\Contracts\ConversationService;
use App\Service\Bot\Implements\ConversationServiceImpl;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use App\Service\Whatsapp\Implements\GowaGatewayServiceImpl;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            WhatsappGatewayService::class,
            GowaGatewayServiceImpl::class
        );

        $this->app->bind(
            ConversationService::class,
            ConversationServiceImpl::class
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
