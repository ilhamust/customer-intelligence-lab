<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
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
            $messageData = $request->input('payload', []);
            Log::info('[GOWA Incoming Message]', [
                'from' => $messageData['from'] ?? null,
                'body' => $messageData['body'] ?? null,
                'is_from_me' => $messageData['is_from_me'] ?? false,
            ]);

            // TODO: ProcessIncomingWhatsappMessage::dispatch($payload);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Webhook received',
            'event' => $event,
        ]);
    }
}
