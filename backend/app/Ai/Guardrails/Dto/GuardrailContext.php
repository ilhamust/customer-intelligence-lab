<?php

namespace App\Ai\Guardrails\Dto;

use App\Models\Bot\Conversation;
use App\Models\Bot\Customer;
use App\Models\Bot\Message;

class GuardrailContext
{
    public function __construct(
        public ?Conversation $conversation = null,
        public ?Customer $customer = null,
        public ?Message $incomingMessage = null,
        public array $metadata = []
    ) {
        // Otomatis sinkronkan customer dari relasi conversation jika belum diset
        if (! $this->customer && $this->conversation?->customer) {
            $this->customer = $this->conversation->customer;
        }
    }

    /**
     * Dapatkan nama pelanggan yang ramah untuk disapa.
     */
    public function getCustomerName(): string
    {
        return $this->customer?->name 
            ?: ($this->conversation?->name ?: 'Pelanggan');
    }

    /**
     * Dapatkan nomor telepon pelanggan.
     */
    public function getCustomerPhone(): string
    {
        return $this->customer?->phone 
            ?: ($this->conversation?->phone ?: '-');
    }

    /**
     * Dapatkan ID percakapan aktif.
     */
    public function getConversationId(): ?string
    {
        return $this->conversation?->id;
    }

    /**
     * Dapatkan ID device WhatsApp yang digunakan.
     */
    public function getDeviceId(): ?string
    {
        return $this->conversation?->device_id;
    }

    /**
     * Tambahkan metadata dinamis ke konteks.
     */
    public function withMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Ambil metadata tertentu atau seluruh array metadata.
     */
    public function getMetadata(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? $default;
    }
}
