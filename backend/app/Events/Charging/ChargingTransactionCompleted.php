<?php

namespace App\Events\Charging;

use App\Models\Charging\ChargingTransaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChargingTransactionCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ChargingTransaction $transaction,
        public bool $isNewCustomerLinked = false
    ) {
        $this->transaction->loadMissing(['customer', 'pointsLedger']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('charging'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ChargingTransactionCompleted';
    }

    /**
     * Data payload to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'transaction' => $this->transaction,
            'is_new_customer_linked' => $this->isNewCustomerLinked,
        ];
    }
}
