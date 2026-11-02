<?php

namespace App\Events\Charging;

use App\Models\Charging\ChargingTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChargingTransactionCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ChargingTransaction $transaction,
        public bool $isNewCustomerLinked = false
    ) {}
}
