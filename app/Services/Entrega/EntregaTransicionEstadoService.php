<?php

namespace App\Services\Entrega;

use App\Enums\CodigoEstadoEntrega;
use App\Enums\CodigoQuienEntrega;
use App\Enums\CodigoTipoDespacho;
use App\Exceptions\Entrega\TransicionInvalidaException;
use App\Models\Entrega;
use App\Models\EstadoEntrega;

class EntregaTransicionEstadoService
{
    /** Mapa de transiciones permitidas: estado_actual => [estados_destino] */
    private const TRANSICIONES_VALIDAS = [
        'pe' => ['ec', 'en', 'ca'],
        'ec' => ['en', 'ca'],
        'en' => ['ca'],   // solo anulación post-entrega (excepcional)
        'ca' => [],       // estado final, sin salida
    ];

    /**
     * Determina el estado inicial al crear una entrega según la lógica de negocio.
     *
     * Matriz:
     *   vendedor + inmediato  → entregado (en)    [vendedor hace entrega directa]
     *   chofer   + inmediato  → en camino (ec)    [sale ahora]
     *   cualquier otro        → pendiente (pe)
     */
    public function estadoInicial(
        CodigoQuienEntrega $quienEntrega,
        ?CodigoTipoDespacho $tipoDespacho
    ): CodigoEstadoEntrega {
        return match (true) {
            $quienEntrega === CodigoQuienEntrega::Vendedor
                && $tipoDespacho === CodigoTipoDespacho::Inmediato
                => CodigoEstadoEntrega::Entregado,

            $quienEntrega === CodigoQuienEntrega::Chofer
                && $tipoDespacho === CodigoTipoDespacho::Inmediato
                => CodigoEstadoEntrega::EnCamino,

            default => CodigoEstadoEntrega::Pendiente,
        };
    }

    /**
     * Verifica si la transición está permitida desde el estado actual.
     */
    public function puedeTransicionarA(Entrega $entrega, CodigoEstadoEntrega $nuevo): bool
    {
        $actual = $entrega->estadoEntrega->codigo->value;
        return in_array($nuevo->value, self::TRANSICIONES_VALIDAS[$actual] ?? [], true);
    }

    /**
     * Ejecuta la transición de estado y actualiza los campos de auditoría.
     *
     * @throws TransicionInvalidaException
     */
    public function transicionar(
        Entrega $entrega,
        CodigoEstadoEntrega $nuevo,
        ?string $userId = null
    ): void {
        $actual = $entrega->estadoEntrega->codigo->value;

        if (! $this->puedeTransicionarA($entrega, $nuevo)) {
            throw new TransicionInvalidaException($actual, $nuevo->value);
        }

        $estadoId = EstadoEntrega::where('codigo', $nuevo->value)->value('id');

        $cambios = ['estado_entrega_id' => $estadoId];

        if ($nuevo === CodigoEstadoEntrega::Entregado) {
            $cambios['fecha_ejecutada']    = now();
            $cambios['user_entregado_id'] = $userId;
        }

        if ($nuevo === CodigoEstadoEntrega::Cancelado) {
            $cambios['fecha_anulacion']    = now();
            $cambios['user_anulacion_id'] = $userId;
        }

        $entrega->update($cambios);
        $entrega->refresh();
    }
}
