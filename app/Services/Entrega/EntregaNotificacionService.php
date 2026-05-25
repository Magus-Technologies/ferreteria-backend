<?php

namespace App\Services\Entrega;

use App\Models\Entrega;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Log;

class EntregaNotificacionService
{
    public function __construct(
        private FirebaseNotificationService $firebase
    ) {}

    /**
     * Notifica al chofer cuando se le asigna una entrega.
     */
    public function notificarAsignacion(Entrega $entrega): void
    {
        $entrega->loadMissing('venta');
        $venta = $entrega->venta;
        $label = $venta ? "{$venta->serie}-{$venta->numero}" : "#{$entrega->id}";

        $datos = [
            'type'        => 'pedido_entrega',
            'entrega_id'  => (string) $entrega->id,
            'tipo_pedido' => $entrega->tipo_pedido,
            'venta_serie' => $venta->serie ?? '',
            'venta_numero'=> $venta->numero ?? '',
            'direccion'   => $entrega->direccion_entrega ?? '',
        ];

        $cuerpo = "Venta {$label}" .
            ($entrega->direccion_entrega ? " a {$entrega->direccion_entrega}" : '');

        // Pedido externo: broadcast por cargo
        if ($entrega->tipo_pedido === 'externo' && $entrega->cargo_destino) {
            $this->firebase->sendToCargo(
                $entrega->cargo_destino,
                'Nueva Entrega Disponible',
                $cuerpo,
                $datos
            );
            return;
        }

        // Pedido interno: notificación directa al chofer
        if ($entrega->chofer_id) {
            $chofer = User::find($entrega->chofer_id);
            if ($chofer && $chofer->fcm_token) {
                $this->firebase->sendNotification(
                    $chofer->fcm_token,
                    'Nueva Entrega Programada',
                    $cuerpo,
                    $datos
                );
            }
        }
    }

    /**
     * Notifica al chofer cuando se le reasigna una entrega.
     */
    public function notificarReasignacion(Entrega $entrega, string $choferAnteriorId): void
    {
        // Notificar al nuevo chofer
        $this->notificarAsignacion($entrega);

        // Log para auditoría
        Log::info("Entrega #{$entrega->id} reasignada de chofer {$choferAnteriorId} a {$entrega->chofer_id}");
    }
}
