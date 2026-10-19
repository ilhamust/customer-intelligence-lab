<?php

namespace App\Service\Bot\Contracts;

use App\Models\Bot\Conversation;
use App\Models\Bot\Customer;

interface CustomerRegistrationService
{
    /**
     * Get the formatted WhatsApp text template for the registration form.
     */
    public function getRegistrationFormText(): string;

    /**
     * Parse and extract customer registration fields from a chat reply.
     *
     * @return array{
     *     name: string,
     *     vehicle: string,
     *     driver_type: string,
     *     residential_area: string,
     *     primary_route: string
     * }|null Returns null if required fields cannot be extracted
     */
    public function parseRegistrationText(string $text): ?array;

    /**
     * Register a new customer, persist to database, and link to conversation.
     *
     * @param  array{
     *     name: string,
     *     vehicle: string,
     *     driver_type: string,
     *     residential_area?: string|null,
     *     primary_route?: string|null
     * }  $data
     */
    public function registerCustomer(Conversation $conversation, array $data): Customer;

    /**
     * Build the official WhatsApp welcome message text containing customer ID and Wi-Fi token.
     *
     * @param  array{
     *     ssid: string,
     *     username: string,
     *     password: string,
     *     duration_minutes: int,
     *     expires_at_formatted: string
     * }  $wifiToken
     */
    public function buildWelcomeMessageText(Customer $customer, array $wifiToken): string;

    /**
     * Get the gentle reminder guide text when registration input is incomplete.
     */
    public function getIncompleteGuideText(): string;
}
