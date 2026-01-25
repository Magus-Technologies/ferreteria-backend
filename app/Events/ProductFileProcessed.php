<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductFileProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $productId,
        public string $fileType,
        public string $filePath,
        public int $userId,
        public string $processingId
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('file-processing'),
            new PrivateChannel("user.{$this->userId}"),
            new PrivateChannel("processing.{$this->processingId}"),
            new PrivateChannel("product.{$this->productId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'product.file.processed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'file_type' => $this->fileType,
            'file_path' => $this->filePath,
            'file_url' => asset('storage/' . $this->filePath),
            'processing_id' => $this->processingId,
            'user_id' => $this->userId,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Determine if this event should be queued for broadcasting.
     */
    public function shouldQueue(): bool
    {
        return true;
    }
}
