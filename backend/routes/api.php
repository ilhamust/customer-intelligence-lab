<?php

use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use App\Http\Middleware\VerifyHmacSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/whatsapp', WhatsappWebhookController::class)
        ->middleware(VerifyHmacSignature::class)
        ->name('webhooks.whatsapp');
});
