<?php

use App\Http\Controllers\Bot\ConversationController;
use App\Http\Controllers\Bot\CustomerController;
use App\Http\Controllers\Bot\TicketController;
use App\Http\Controllers\Webhooks\WhatsappWebhookController;
use App\Http\Controllers\Whatsapp\WhatsappDeviceController;
use App\Http\Middleware\VerifyHmacSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/whatsapp', WhatsappWebhookController::class)
        ->middleware(VerifyHmacSignature::class)
        ->name('webhooks.whatsapp');

    // WhatsApp Device Management for Frontend Back Office
    Route::prefix('whatsapp')->group(function () {
        Route::get('/devices', [WhatsappDeviceController::class, 'index'])->name('whatsapp.devices.index');
        Route::post('/devices', [WhatsappDeviceController::class, 'register'])->name('whatsapp.devices.register');
        Route::delete('/devices/{deviceId}', [WhatsappDeviceController::class, 'destroy'])->name('whatsapp.devices.destroy');
        Route::get('/status', [WhatsappDeviceController::class, 'status'])->name('whatsapp.status');
        Route::post('/login-qr', [WhatsappDeviceController::class, 'loginQr'])->name('whatsapp.login.qr');
        Route::post('/login-code', [WhatsappDeviceController::class, 'loginCode'])->name('whatsapp.login.code');
        Route::post('/logout', [WhatsappDeviceController::class, 'logout'])->name('whatsapp.logout');
        Route::post('/reconnect', [WhatsappDeviceController::class, 'reconnect'])->name('whatsapp.reconnect');
    });

    // Customers Management for Frontend Back Office
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/{id}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/{id}/points', [CustomerController::class, 'points'])->name('customers.points');
        Route::get('/{id}/ledger', [CustomerController::class, 'ledger'])->name('customers.ledger');
    });

    // Conversations & Chat Management
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('/sync', [ConversationController::class, 'sync'])->name('conversations.sync');
        Route::get('/{id}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::get('/{id}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
        Route::post('/{id}/messages', [ConversationController::class, 'send'])->name('conversations.messages.send');
        Route::post('/{id}/typing', [ConversationController::class, 'typing'])->name('conversations.typing');
        Route::patch('/{id}/handler', [ConversationController::class, 'updateHandler'])->name('conversations.handler.update');
    });

    // Customer Support Tickets & Queue Management
    Route::prefix('tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/status', [TicketController::class, 'status'])->name('tickets.status');
        Route::post('/{id}/serve', [TicketController::class, 'serve'])->name('tickets.serve');
        Route::post('/{id}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
    });
});
