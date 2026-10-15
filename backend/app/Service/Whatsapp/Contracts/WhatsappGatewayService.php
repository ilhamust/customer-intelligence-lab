<?php

namespace App\Service\Whatsapp\Contracts;

use App\Models\Bot\WhatsappChannel;

interface WhatsappGatewayService
{
    /**
     * Get device connection and login status from GOWA and synchronize with DB.
     *
     * @param string|null $deviceId
     * @return array
     */
    public function getStatus(?string $deviceId = null): array;

    /**
     * Register a new device in GOWA and persist to whatsapp_channels table.
     *
     * @param string $deviceId
     * @param string $name
     * @return WhatsappChannel
     */
    public function registerDevice(string $deviceId, string $name): WhatsappChannel;

    /**
     * Request QR Code login from GOWA and convert image in-memory to Base64 data URL.
     *
     * @param string $deviceId
     * @return array{device_id: string, qr_image: string, duration: int}
     */
    public function getLoginQr(string $deviceId): array;

    /**
     * Request 8-digit pairing code for login using phone number.
     *
     * @param string $deviceId
     * @param string $phone
     * @return array{device_id: string, pair_code: string}
     */
    public function loginWithCode(string $deviceId, string $phone): array;

    /**
     * Logout and terminate WhatsApp session on GOWA.
     *
     * @param string $deviceId
     * @return bool
     */
    public function logout(string $deviceId): bool;

    /**
     * Reconnect device to WhatsApp server.
     *
     * @param string $deviceId
     * @return bool
     */
    public function reconnect(string $deviceId): bool;

    /**
     * Send outgoing text message via GOWA REST API.
     *
     * @param string $phone
     * @param string $message
     * @param string|null $deviceId
     * @return bool
     */
    public function sendText(string $phone, string $message, ?string $deviceId = null): bool;
}
