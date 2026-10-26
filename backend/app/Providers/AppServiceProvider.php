<?php

namespace App\Providers;

use App\Service\Bot\Contracts\ConversationService;
use App\Service\Bot\Contracts\CustomerRegistrationService;
use App\Service\Bot\Contracts\TicketService;
use App\Service\Bot\Contracts\WifiTokenService;
use App\Service\Bot\Implements\ConversationServiceImpl;
use App\Service\Bot\Implements\CustomerRegistrationServiceImpl;
use App\Service\Bot\Implements\TicketServiceImpl;
use App\Service\Bot\Implements\WifiTokenServiceImpl;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use App\Service\Whatsapp\Implements\GowaGatewayServiceImpl;
use App\Ai\Guardrails\GuardrailManager;
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

        $this->app->bind(
            WifiTokenService::class,
            WifiTokenServiceImpl::class
        );

        $this->app->bind(
            CustomerRegistrationService::class,
            CustomerRegistrationServiceImpl::class
        );

        $this->app->bind(
            TicketService::class,
            TicketServiceImpl::class
        );

        $this->app->singleton(GuardrailManager::class, function () {
            return new GuardrailManager();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
