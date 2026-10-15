<?php

use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use App\Http\Middleware\VerifyHmacSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/whatsapp', WhatsappWebhookController::class)
        ->middleware(VerifyHmacSignature::class)
        ->name('webhooks.whatsapp');

    // WhatsApp Device Management for Frontend Back Office
    Route::prefix('whatsapp')->group(function () {
        Route::get('/devices', [\App\Http\Controllers\Whatsapp\WhatsappDeviceController::class, 'index'])->name('whatsapp.devices.index');
        Route::post('/devices', [\App\Http\Controllers\Whatsapp\WhatsappDeviceController::class, 'register'])->name('whatsapp.devices.register');
        Route::delete('/devices/{deviceId}', [\App\Http\Controllers\Whatsapp\WhatsappDeviceController::class, 'destroy'])->name('whatsapp.devices.destroy');
        Route::get('/status', [\App\Http\Controllers\Whatsapp\WhatsappDeviceController::class, 'status'])->name('whatsapp.status');
        Route::post('/login-qr', [\App\Http\Controllers\Whatsapp\WhatsappDeviceController::class, 'loginQr'])->name('whatsapp.login.qr');
        Route::post('/login-code', [\App\Http\Controllers\Whatsapp\WhatsappDeviceController::class, 'loginCode'])->name('whatsapp.login.code');
        Route::post('/logout', [\App\Http\Controllers\Whatsapp\WhatsappDeviceController::class, 'logout'])->name('whatsapp.logout');
        Route::post('/reconnect', [\App\Http\Controllers\Whatsapp\WhatsappDeviceController::class, 'reconnect'])->name('whatsapp.reconnect');
    });

    // Conversations & Chat Management
    Route::prefix('conversations')->group(function () {
        Route::get('/', [\App\Http\Controllers\Bot\ConversationController::class, 'index'])->name('conversations.index');
        Route::post('/sync', [\App\Http\Controllers\Bot\ConversationController::class, 'sync'])->name('conversations.sync');
        Route::get('/{id}', [\App\Http\Controllers\Bot\ConversationController::class, 'show'])->name('conversations.show');
        Route::get('/{id}/messages', [\App\Http\Controllers\Bot\ConversationController::class, 'messages'])->name('conversations.messages');
        Route::post('/{id}/messages', [\App\Http\Controllers\Bot\ConversationController::class, 'send'])->name('conversations.messages.send');
    });
});
