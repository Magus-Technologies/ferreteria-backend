<?php

namespace App\Services\Entrega;

use App\Models\Entrega;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Log;

class EntregaNotificacionService
{
    private const PERMISO_MODULO_ENTREGAS = 'facturacion-electronica.mis-entregas.index';

    public function __construct(
        private FirebaseNotificationService $firebase
    ) {}

    /**
     * Notifica al chofer cuando se le asigna una entrega,
     * y también a todos los usuarios con acceso al módulo de entregas.
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

        $titulo = $entrega->tipo_pedido === 'externo'
            ? 'Nueva Entrega Disponible'
            : 'Nueva Entrega Programada';

        $notifiedTokens = [];

        // Pedido externo: broadcast por cargo
        if ($entrega->tipo_pedido === 'externo' && $entrega->cargo_destino) {
            $this->firebase->sendToCargo(
                $entrega->cargo_destino,
                $titulo,
                $cuerpo,
                $datos
            );

            $cargoTokens = User::where('cargo', $entrega->cargo_destino)
                ->whereNotNull('fcm_token')
                ->where('estado', true)
                ->pluck('fcm_token')
                ->toArray();
            $notifiedTokens = array_merge($notifiedTokens, $cargoTokens);
        }

        // Pedido interno: notificación directa al chofer
        if ($entrega->chofer_id) {
            $chofer = User::find($entrega->chofer_id);
            if ($chofer && $chofer->fcm_token) {
                $sent = $this->firebase->sendNotification(
                    $chofer->fcm_token,
                    $titulo,
                    $cuerpo,
                    $datos
                );
                // Only exclude from broadcast if the direct send actually succeeded.
                // If it failed, the chofer will still receive it via the module broadcast.
                if ($sent) {
                    $notifiedTokens[] = $chofer->fcm_token;
                }
            }
        }

        // Broadcast a todos los usuarios con acceso al módulo de entregas
        try {
            $this->firebase->sendToUsersWithModuleAccess(
                self::PERMISO_MODULO_ENTREGAS,
                $titulo,
                $cuerpo,
                $datos,
                $notifiedTokens
            );
        } catch (\Exception $e) {
            Log::channel('firebase')->warning('Error notificando a usuarios del módulo de entregas', [
                'message' => $e->getMessage(),
                'entrega_id' => $entrega->id,
            ]);
        }
    }

    /**
     * Notifica al chofer cuando se le reasigna una entrega.
     */
    public function notificarReasignacion(Entrega $entrega, string $choferAnteriorId): void
    {
        $this->notificarAsignacion($entrega);

        Log::channel('firebase')->info("Entrega #{$entrega->id} reasignada de chofer {$choferAnteriorId} a {$entrega->chofer_id}");
    }

    /**
     * Notifica al chofer y a los usuarios del módulo que la entrega
     * está "en camino".
     */
    public function notificarEnCamino(Entrega $entrega): void
    {
        $entrega->loadMissing('venta');
        $venta = $entrega->venta;
        $label = $venta ? "{$venta->serie}-{$venta->numero}" : "#{$entrega->id}";

        $datos = [
            'type'        => 'entrega_en_camino',
            'entrega_id'  => (string) $entrega->id,
            'venta_serie' => $venta->serie ?? '',
            'venta_numero'=> $venta->numero ?? '',
            'direccion'   => $entrega->direccion_entrega ?? '',
            'link'        => '/ui/facturacion-electronica/mis-entregas',
        ];

        $cuerpo = "Venta {$label}" .
            ($entrega->direccion_entrega ? " — {$entrega->direccion_entrega}" : '');

        $titulo = 'Entrega en Camino';

        $notifiedTokens = [];

        if ($entrega->chofer_id) {
            $chofer = User::find($entrega->chofer_id);
            if ($chofer && $chofer->fcm_token) {
                $this->firebase->sendNotification(
                    $chofer->fcm_token,
                    $titulo,
                    $cuerpo,
                    $datos
                );
                $notifiedTokens[] = $chofer->fcm_token;
            }
        }

        try {
            $this->firebase->sendToUsersWithModuleAccess(
                self::PERMISO_MODULO_ENTREGAS,
                $titulo,
                $cuerpo,
                $datos,
                $notifiedTokens
            );
        } catch (\Exception $e) {
            Log::channel('firebase')->warning('Error notificando en camino', [
                'message' => $e->getMessage(),
                'entrega_id' => $entrega->id,
            ]);
        }
    }

    /**
     * Notifica a todos los usuarios con acceso al módulo que una entrega
     * fue completada (estado "Entregado"). La notificación apunta al
     * calendario de entregas.
     */
    public function notificarCompletada(Entrega $entrega): void
    {
        $entrega->loadMissing('venta');
        $venta = $entrega->venta;
        $label = $venta ? "{$venta->serie}-{$venta->numero}" : "#{$entrega->id}";

        $datos = [
            'type'        => 'entrega_completada',
            'entrega_id'  => (string) $entrega->id,
            'venta_serie' => $venta->serie ?? '',
            'venta_numero'=> $venta->numero ?? '',
            'direccion'   => $entrega->direccion_entrega ?? '',
            'link'        => '/ui/facturacion-electronica/mis-ventas/calendario',
        ];

        $cuerpo = "Venta {$label}" .
            ($entrega->direccion_entrega ? " — {$entrega->direccion_entrega}" : '');

        try {
            $this->firebase->sendToUsersWithModuleAccess(
                self::PERMISO_MODULO_ENTREGAS,
                '✅ Entrega Completada',
                $cuerpo,
                $datos
            );
        } catch (\Exception $e) {
            Log::channel('firebase')->warning('Error notificando completada', [
                'message' => $e->getMessage(),
                'entrega_id' => $entrega->id,
            ]);
        }
    }
}
