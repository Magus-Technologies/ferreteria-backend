<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductoImported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $productoId,
        public array $productData,
        public int $userId,
        public string $importId
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('imports'),
            new PrivateChannel("user.{$this->userId}"),
            new PrivateChannel("import.{$this->importId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'producto.imported';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'producto_id' => $this->productoId,
            'product_name' => $this->productData['name'] ?? 'Unknown',
            'product_code' => $this->productData['cod_producto'] ?? null,
            'import_id' => $this->importId,
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
