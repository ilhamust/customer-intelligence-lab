<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Service\Bot\Contracts\ConversationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService
    ) {}

    /**
     * Handle incoming webhook notifications from GOWA service.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $request->input('event', 'unknown');
        $deviceId = $request->input('device_id');

        Log::info('[GOWA Webhook Received]', [
            'event' => $event,
            'device_id' => $deviceId,
            'raw_keys' => array_keys($payload),
        ]);

        // Jika event adalah pesan WhatsApp masuk
        if ($event === 'message') {
            try {
                $savedMessage = $this->conversationService->handleIncomingMessageWebhook($payload, $deviceId);

                Log::info('[GOWA Message Saved]', [
                    'device_id' => $deviceId,
                    'message_id' => $savedMessage?->id,
                ]);
            } catch (Exception $e) {
                Log::error('[GOWA Message Webhook Error]', [
                    'device_id' => $deviceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Webhook received',
            'event' => $event,
        ]);
    }
}
