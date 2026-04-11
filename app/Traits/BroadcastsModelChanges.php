<?php

namespace App\Traits;

use App\Events\ModelChanged;

/**
 * Trait para despachar eventos de cambio de modelo desde controllers.
 * Uso: $this->broadcastChange('productos', 'created', $producto->id);
 */
trait BroadcastsModelChanges
{
    protected function broadcastChange(string $module, string $action = 'updated', ?string $recordId = null): void
    {
        $userId = auth()->id();

        event(new ModelChanged(
            module: $module,
            action: $action,
            recordId: $recordId,
            userId: $userId ? (int) $userId : null,
        ));
    }
}
