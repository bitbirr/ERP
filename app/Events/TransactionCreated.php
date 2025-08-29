<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TransactionCreated implements ShouldBroadcast
{
    public function __construct(public array $payload) {}

    public function broadcastOn(): Channel
    {
        return new Channel('transactions'); // public channel for simplicity
    }

    public function broadcastAs(): string
    {
        return 'created';
    }
}
