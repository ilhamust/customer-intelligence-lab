<?php

namespace App\Service\Whatsapp\Contracts;

use App\Models\Bot\WhatsappChannel;

interface WhatsappGatewayService
{
    /**
     * Get device connection and login status from GOWA and synchronize with DB.
     */
    public function getStatus(?string $deviceId = null): array;

    /**
     * Register a new device in GOWA and persist to whatsapp_channels table.
     */
    public function registerDevice(string $deviceId, string $name): WhatsappChannel;

    /**
     * Request QR Code login from GOWA and convert image in-memory to Base64 data URL.
     *
     * @return array{device_id: string, qr_image: string, duration: int}
     */
    public function getLoginQr(string $deviceId): array;

    /**
     * Request 8-digit pairing code for login using phone number.
     *
     * @return array{device_id: string, pair_code: string}
     */
    public function loginWithCode(string $deviceId, string $phone): array;

    /**
     * Logout and terminate WhatsApp session on GOWA.
     */
    public function logout(string $deviceId): bool;

    /**
     * Reconnect device to WhatsApp server.
     */
    public function reconnect(string $deviceId): bool;

    /**
     * Send outgoing text message via GOWA REST API.
     */
    public function sendText(string $phone, string $message, ?string $deviceId = null): bool;

    /**
     * Send chat presence (typing indicator) via GOWA REST API.
     *
     * @param  string  $action  'start' or 'stop'
     */
    public function sendChatPresence(string $phone, string $action = 'start', ?string $deviceId = null): bool;

    /**
     * Get list of all registered WhatsApp devices and their current status.
     */
    public function listDevices(): array;

    /**
     * Delete a device from GOWA and remove from database.
     */
    public function deleteDevice(string $deviceId): bool;

    /**
     * Get list of chats from GOWA.
     */
    public function getChats(?string $deviceId = null, int $limit = 50, int $offset = 0): array;

    /**
     * Get list of messages for a specific chat from GOWA.
     */
    public function getChatMessages(string $chatJid, ?string $deviceId = null, int $limit = 50, int $offset = 0): array;

    /**
     * Update device webhook configuration in GOWA.
     */
    public function setDeviceWebhook(string $deviceId, ?string $webhookUrl = null): bool;
}
