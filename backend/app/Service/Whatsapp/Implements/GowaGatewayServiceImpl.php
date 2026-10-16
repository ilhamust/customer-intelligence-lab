<?php

namespace App\Service\Whatsapp\Implements;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\NotFoundException;
use App\Models\Bot\WhatsappChannel;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use App\Traits\ServiceHelper;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GowaGatewayServiceImpl implements WhatsappGatewayService
{
    use ServiceHelper;

    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected string $defaultDeviceId;
    protected string $webhookSecret;
    protected ?string $webhookUrl;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.gowa.base_url', 'http://gowa:3000');
        $this->username = (string) config('services.gowa.username', 'user1');
        $this->password = (string) config('services.gowa.password', 'pass1');
        $this->defaultDeviceId = (string) config('services.gowa.default_device_id', 'default');
        $this->webhookSecret = (string) config('services.gowa.webhook_secret', '');
        $this->webhookUrl = config('services.gowa.target_webhook_url');
    }

    /**
     * Create an authenticated HTTP client for GOWA API calls.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->username, $this->password)
            ->timeout(15);
    }

    /**
     * Resolve device ID falling back to default device if null.
     */
    protected function resolveDeviceId(?string $deviceId): string
    {
        return $deviceId ?: $this->defaultDeviceId;
    }

    /**
     * Resolve target webhook URL for GOWA callbacks.
     */
    protected function resolveWebhookUrl(): string
    {
        $url = $this->webhookUrl ?: config('services.gowa.target_webhook_url');

        if (!empty($url)) {
            // When running in Docker/Sail, replace localhost/127.0.0.1 with laravel.test
            if (env('LARAVEL_SAIL') && (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1'))) {
                return 'http://laravel.test/api/v1/webhooks/whatsapp';
            }
            return $url;
        }

        return env('LARAVEL_SAIL') ? 'http://laravel.test/api/v1/webhooks/whatsapp' : url('/api/v1/webhooks/whatsapp');
    }

    /**
     * Get connection and login status of a device.
     */
    public function getStatus(?string $deviceId = null): array
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        try {
            $response = $this->client()->get("/devices/{$id}/status");

            if ($response->status() === 404 || str_contains($response->body(), 'not found')) {
                // Device not found in GOWA, mark DB record as disconnected
                WhatsappChannel::where('device_id', $id)->update(['status' => 'disconnected']);
                return [
                    'device_id' => $id,
                    'is_registered' => false,
                    'is_connected' => false,
                    'is_logged_in' => false,
                    'status' => 'disconnected',
                    'jid' => null,
                ];
            }

            if (!$response->successful()) {
                throw new InternalServerException('Failed to get device status from GOWA: ' . $response->body());
            }

            $results = $response->json('results', []);
            $isConnected = (bool) ($results['is_connected'] ?? false);
            $isLoggedIn = (bool) ($results['is_logged_in'] ?? false);
            $jid = $results['jid'] ?? null;

            $status = $isLoggedIn ? 'connected' : ($isConnected ? 'pairing' : 'disconnected');

            // Sync with DB
            $channel = WhatsappChannel::where('device_id', $id)->first();
            if ($channel) {
                $channel->update([
                    'status' => $status,
                    'jid' => $jid,
                    'phone_number' => $jid ? explode('@', $jid)[0] : $channel->phone_number,
                ]);
            } else {
                WhatsappChannel::create([
                    'device_id' => $id,
                    'name' => 'Gateway ' . $id,
                    'status' => $status,
                    'jid' => $jid,
                    'phone_number' => $jid ? explode('@', $jid)[0] : null,
                    'is_active' => true,
                ]);
            }

            return [
                'device_id' => $id,
                'is_registered' => true,
                'is_connected' => $isConnected,
                'is_logged_in' => $isLoggedIn,
                'status' => $status,
                'jid' => $jid,
            ];
        } catch (Exception $e) {
            $this->logError($m, 'Error fetching device status', ['device_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Register a new device in GOWA and database.
     */
    public function registerDevice(string $deviceId, string $name): WhatsappChannel
    {
        $m = __METHOD__;

        $targetWebhook = $this->resolveWebhookUrl();

        $response = $this->client()->post('/devices', [
            'device_id' => $deviceId,
            'webhook_url' => $targetWebhook,
            'webhook_secret' => $this->webhookSecret,
            'webhook_events' => 'message,message.ack',
            'webhook_insecure_skip_verify' => false,
        ]);

        if (!$response->successful() && $response->status() !== 409) {
            $this->logError($m, 'Failed to register device in GOWA', [
                'device_id' => $deviceId,
                'response' => $response->body(),
            ]);
            throw new BadRequestException('Failed to register device in GOWA: ' . $response->body());
        }

        $channel = WhatsappChannel::updateOrCreate(
            ['device_id' => $deviceId],
            [
                'name' => $name,
                'status' => 'disconnected',
                'is_active' => true,
            ]
        );

        $this->logInfo($m, 'Device registered successfully', ['device_id' => $deviceId, 'name' => $name]);

        return $channel;
    }

    /**
     * Request QR Code login and convert in-memory to Base64 data URL.
     */
    public function getLoginQr(string $deviceId): array
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        // Ensure device is registered first
        $this->ensureDeviceExists($id);

        $response = $this->client()->get("/devices/{$id}/login");

        if (!$response->successful()) {
            $this->logError($m, 'Failed to request login QR from GOWA', [
                'device_id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new BadRequestException('Failed to get QR code: ' . $response->json('message', $response->body()));
        }

        $results = $response->json('results', []);
        $qrLink = $results['qr_link'] ?? null;
        $duration = (int) ($results['qr_duration'] ?? 30);

        if (!$qrLink) {
            throw new InternalServerException('QR link missing from GOWA response');
        }

        // Download QR image into memory
        $imageResponse = $this->client()->get($qrLink);

        if (!$imageResponse->successful()) {
            throw new InternalServerException('Failed to download QR code image from GOWA');
        }

        // Convert in-memory bytes to Base64 Data URL (zero disk footprint)
        $base64Image = 'data:image/png;base64,' . base64_encode($imageResponse->body());

        WhatsappChannel::where('device_id', $id)->update(['status' => 'pairing']);

        $this->logInfo($m, 'Login QR generated in-memory', ['device_id' => $id, 'duration' => $duration]);

        return [
            'device_id' => $id,
            'qr_image' => $base64Image,
            'duration' => $duration,
        ];
    }

    /**
     * Login using an 8-character pairing code.
     */
    public function loginWithCode(string $deviceId, string $phone): array
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        $this->ensureDeviceExists($id);

        $response = $this->client()->post("/devices/{$id}/login/code?phone={$phone}");

        if (!$response->successful()) {
            throw new BadRequestException('Failed to get pairing code: ' . $response->json('message', $response->body()));
        }

        $results = $response->json('results', []);
        $pairCode = $results['pair_code'] ?? '';

        WhatsappChannel::where('device_id', $id)->update(['status' => 'pairing', 'phone_number' => $phone]);

        $this->logInfo($m, 'Pairing code generated', ['device_id' => $id, 'phone' => $phone]);

        return [
            'device_id' => $id,
            'pair_code' => $pairCode,
        ];
    }

    /**
     * Logout and clear WhatsApp session.
     */
    public function logout(string $deviceId): bool
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        $response = $this->client()->post("/devices/{$id}/logout");

        WhatsappChannel::where('device_id', $id)->update([
            'status' => 'disconnected',
            'jid' => null,
        ]);

        $this->logInfo($m, 'Device logged out', ['device_id' => $id]);

        return $response->successful();
    }

    /**
     * Reconnect device to WhatsApp.
     */
    public function reconnect(string $deviceId): bool
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        $response = $this->client()->post("/devices/{$id}/reconnect");

        $this->logInfo($m, 'Device reconnect requested', ['device_id' => $id]);

        return $response->successful();
    }

    /**
     * Send outgoing text message via GOWA REST API.
     */
    public function sendText(string $phone, string $message, ?string $deviceId = null): bool
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        $response = $this->client()
            ->withHeaders(['X-Device-Id' => $id])
            ->post('/send/message', [
                'phone' => $phone,
                'message' => $message,
            ]);

        if (!$response->successful()) {
            $this->logError($m, 'Failed to send text message via GOWA', [
                'device_id' => $id,
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        $this->logInfo($m, 'WhatsApp message sent successfully', ['device_id' => $id, 'phone' => $phone]);

        return true;
    }

    /**
     * Helper to auto-create device in GOWA if not registered yet.
     */
    protected function ensureDeviceExists(string $deviceId): void
    {
        $check = $this->client()->get("/devices/{$deviceId}");
        if ($check->status() === 404) {
            $this->registerDevice($deviceId, 'Gateway ' . $deviceId);
        }
    }

    /**
     * Get list of all registered WhatsApp devices and their current status.
     */
    public function listDevices(): array
    {
        $m = __METHOD__;

        try {
            // First check GOWA API if devices exist
            $gowaDevices = [];
            try {
                $response = $this->client()->get('/devices');
                if ($response->successful()) {
                    $results = $response->json('results', []);
                    if (is_array($results)) {
                        foreach ($results as $item) {
                            $devId = $item['id'] ?? $item['device_id'] ?? null;
                            if (!empty($devId)) {
                                $gowaDevices[$devId] = $item;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logWarning($m, 'Could not fetch device list directly from GOWA, falling back to database', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Sync with DB
            $channels = WhatsappChannel::orderBy('created_at', 'desc')->get();

            // If DB is empty but GOWA has devices, sync them to DB
            if ($channels->isEmpty() && !empty($gowaDevices)) {
                foreach ($gowaDevices as $devId => $devData) {
                    $state = strtolower($devData['state'] ?? '');
                    $isLoggedIn = (bool) ($devData['is_logged_in'] ?? ($state === 'logged_in'));
                    $isConnected = (bool) ($devData['is_connected'] ?? ($isLoggedIn || $state === 'connected' || $state === 'connecting' || $state === 'pairing'));
                    $jid = $devData['jid'] ?? null;
                    $phone = $devData['phone_number'] ?? ($jid ? explode('@', $jid)[0] : null);
                    $status = $isLoggedIn ? 'connected' : ($isConnected ? 'pairing' : 'disconnected');

                    WhatsappChannel::create([
                        'device_id' => $devId,
                        'name' => $devData['display_name'] ?? $devData['name'] ?? ('Gateway ' . $devId),
                        'status' => $status,
                        'jid' => $jid,
                        'phone_number' => $phone,
                        'is_active' => true,
                    ]);
                }
                $channels = WhatsappChannel::orderBy('created_at', 'desc')->get();
            }

            // Map and format results
            return $channels->map(function ($channel) use ($gowaDevices) {
                $gowaData = $gowaDevices[$channel->device_id] ?? null;
                $status = $channel->status;
                $jid = $channel->jid;
                $phoneNumber = $channel->phone_number;

                if ($gowaData) {
                    $state = strtolower($gowaData['state'] ?? '');
                    $isLoggedIn = (bool) ($gowaData['is_logged_in'] ?? ($state === 'logged_in'));
                    $isConnected = (bool) ($gowaData['is_connected'] ?? ($isLoggedIn || $state === 'connected' || $state === 'connecting' || $state === 'pairing'));
                    $status = $isLoggedIn ? 'connected' : ($isConnected ? 'pairing' : 'disconnected');
                    $jid = $gowaData['jid'] ?? $jid;
                    $phoneNumber = $gowaData['phone_number'] ?? ($jid ? explode('@', $jid)[0] : $phoneNumber);

                    // Sync updated status to DB if changed
                    if ($channel->status !== $status || $channel->jid !== $jid || $channel->phone_number !== $phoneNumber) {
                        $channel->update([
                            'status' => $status,
                            'jid' => $jid,
                            'phone_number' => $phoneNumber,
                        ]);
                    }
                }

                return [
                    'id' => $channel->id,
                    'device_id' => $channel->device_id,
                    'name' => $channel->name,
                    'phone_number' => $phoneNumber,
                    'jid' => $jid,
                    'status' => $status,
                    'is_active' => (bool) $channel->is_active,
                    'created_at' => $channel->created_at?->toIso8601String(),
                    'updated_at' => $channel->updated_at?->toIso8601String(),
                ];
            })->all();
        } catch (Exception $e) {
            $this->logError($m, 'Failed to list WhatsApp devices', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a device from GOWA and database.
     */
    public function deleteDevice(string $deviceId): bool
    {
        $m = __METHOD__;

        $channel = WhatsappChannel::where('device_id', $deviceId)->first();
        if (!$channel) {
            throw new NotFoundException("WhatsApp device [{$deviceId}] not found.");
        }

        try {
            // Delete from GOWA
            $response = $this->client()->delete("/devices/{$deviceId}");
            if (!$response->successful() && $response->status() !== 404) {
                $this->logWarning($m, 'Failed to delete device in GOWA, proceeding with DB removal', [
                    'device_id' => $deviceId,
                    'status' => $response->status(),
                ]);
            }
        } catch (Exception $e) {
            $this->logWarning($m, 'Error calling GOWA delete device endpoint', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
        }

        $channel->delete();

        $this->logInfo($m, 'Device deleted successfully', ['device_id' => $deviceId]);

        return true;
    }

    /**
     * Get list of chats from GOWA.
     */
    public function getChats(?string $deviceId = null, int $limit = 50, int $offset = 0): array
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        try {
            $response = $this->client()
                ->withHeaders(['X-Device-Id' => $id])
                ->get('/chats', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            if (!$response->successful()) {
                $this->logError($m, 'Failed to fetch chats from GOWA', [
                    'device_id' => $id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            return $response->json('results.data', []);
        } catch (Exception $e) {
            $this->logError($m, 'Error fetching chats from GOWA', [
                'device_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get list of messages for a specific chat from GOWA.
     */
    public function getChatMessages(string $chatJid, ?string $deviceId = null, int $limit = 50, int $offset = 0): array
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        try {
            $response = $this->client()
                ->withHeaders(['X-Device-Id' => $id])
                ->get("/chat/{$chatJid}/messages", [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            if (!$response->successful()) {
                $this->logError($m, 'Failed to fetch chat messages from GOWA', [
                    'device_id' => $id,
                    'chat_jid' => $chatJid,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            return $response->json('results.data', []);
        } catch (Exception $e) {
            $this->logError($m, 'Error fetching chat messages from GOWA', [
                'device_id' => $id,
                'chat_jid' => $chatJid,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Update device webhook configuration in GOWA.
     */
    public function setDeviceWebhook(string $deviceId, ?string $webhookUrl = null): bool
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);
        $url = $webhookUrl ?: $this->resolveWebhookUrl();

        try {
            $response = $this->client()->patch("/devices/{$id}/webhook", [
                'webhook_url' => $url,
                'webhook_secret' => $this->webhookSecret,
                'webhook_events' => 'message,message.ack',
                'webhook_insecure_skip_verify' => false,
            ]);

            if (!$response->successful()) {
                $this->logError($m, 'Failed to update device webhook in GOWA', [
                    'device_id' => $id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $this->logInfo($m, 'Device webhook updated successfully in GOWA', [
                'device_id' => $id,
                'webhook_url' => $url,
            ]);

            return true;
        } catch (Exception $e) {
            $this->logError($m, 'Error updating device webhook in GOWA', [
                'device_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

