<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRTCSignal implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $type,
        public array $data,
        public int $senderId,
        public int $receiverId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('webrtc.' . $this->receiverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webrtc.signal';
    }
}