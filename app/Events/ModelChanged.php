<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento genérico que notifica a todos los clientes conectados
 * que un modelo/módulo ha cambiado y deben refrescar sus datos.
 *
 * Se envía por canal público para simplificar la implementación.
 * No transmite datos sensibles, solo el nombre del módulo que cambió.
 */
class ModelChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $module  Nombre del módulo (ej: 'productos', 'ventas', 'compras')
     * @param string $action  Tipo de acción: 'created', 'updated', 'deleted'
     * @param string|null $recordId  ID opcional del registro afectado
     * @param int|null $userId  ID del usuario que realizó la acción (para excluirlo en frontend)
     */
    public function __construct(
        public string $module,
        public string $action = 'updated',
        public ?string $recordId = null,
        public ?string $userId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('model-changes'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'model.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'module' => $this->module,
            'action' => $this->action,
            'record_id' => $this->recordId,
            'user_id' => $this->userId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
