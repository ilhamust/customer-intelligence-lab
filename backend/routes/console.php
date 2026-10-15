<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('whatsapp:test', function (\App\Service\Whatsapp\Contracts\WhatsappGatewayService $gateway) {
    $this->info('Testing GOWA status...');
    $status = $gateway->getStatus('default');
    $this->line(json_encode($status, JSON_PRETTY_PRINT));

    $this->info('Testing getLoginQr (in-memory base64)...');
    $qr = $gateway->getLoginQr('default');
    $this->info('Device: ' . $qr['device_id']);
    $this->info('Duration: ' . $qr['duration'] . ' seconds');
    $this->info('QR Base64 prefix: ' . substr($qr['qr_image'], 0, 40) . '...');
    $this->info('QR Base64 length: ' . strlen($qr['qr_image']) . ' chars');
})->purpose('Test GOWA WhatsApp Gateway status');
