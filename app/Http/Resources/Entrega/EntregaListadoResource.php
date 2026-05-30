<?php

namespace App\Http\Resources\Entrega;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Shape liviano — usado en la tabla detalle del frontend */
class EntregaListadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $legacy = $this->relationLoaded('entregaLegacy') ? $this->entregaLegacy : null;
        $chofer = $this->chofer ?: $legacy?->despachador;
        $vehiculo = $this->vehiculo ?: $legacy?->vehiculo;
        $fechaProgramada = $this->fecha_programada ?? $legacy?->fecha_programada;
        $latitud = $this->latitud ?? $legacy?->latitud;
        $longitud = $this->longitud ?? $legacy?->longitud;

        return [
            'id'                      => $this->id,
            'venta_id'                => $this->venta_id,
            'venta_entrega_secuencia' => $this->venta_entrega_secuencia,
            'stock_aplicado'          => (bool) $this->stock_aplicado,
            'entrega_legacy_id'       => $this->entrega_legacy_id,

            'tipo_entrega_codigo'   => $this->tipoEntrega?->codigo?->value,
            'tipo_entrega_nombre'   => $this->tipoEntrega?->nombre,
            'tipo_entrega_icono'    => $this->tipoEntrega?->icono,
            'tipo_entrega_color'    => $this->tipoEntrega?->color,

            'tipo_despacho_codigo'  => $this->tipoDespacho?->codigo?->value,
            'tipo_despacho_nombre'  => $this->tipoDespacho?->nombre,

            'estado_entrega_codigo' => $this->estadoEntrega?->codigo?->value,
            'estado_entrega_nombre' => $this->estadoEntrega?->nombre,
            'estado_entrega_color'  => $this->estadoEntrega?->color,
            'es_final'              => (bool) $this->estadoEntrega?->es_final,

            'quien_entrega_codigo'  => $this->quienEntrega?->codigo?->value,
            'quien_entrega_nombre'  => $this->quienEntrega?->nombre,

            'chofer_id'   => $this->chofer_id ?? $legacy?->chofer_id,
            'chofer_name' => $chofer?->name,

            'vehiculo_id'    => $this->vehiculo_id ?? $legacy?->vehiculo_id,
            'vehiculo_placa' => $vehiculo?->placa,
            'vehiculo_name'  => $vehiculo?->name,

            'tipo_pedido'   => $this->tipo_pedido,
            'cargo_destino' => $this->cargo_destino,

            'fecha_creacion'   => $this->fecha_creacion?->toDateString(),
            'fecha_programada' => $this->formatDate($fechaProgramada),
            'fecha_ejecutada'  => $this->fecha_ejecutada?->toIso8601String(),
            'hora_inicio'      => $this->hora_inicio ?? $legacy?->hora_inicio,
            'hora_fin'         => $this->hora_fin ?? $legacy?->hora_fin,

            'direccion_entrega'  => $this->direccion_entrega ?? $legacy?->direccion_entrega,
            'referencia_entrega' => $this->referencia_entrega ?? $legacy?->referencia_entrega,
            'latitud'            => $latitud !== null ? (float) $latitud : null,
            'longitud'           => $longitud !== null ? (float) $longitud : null,
            'observaciones'      => $this->observaciones ?? $legacy?->observaciones,
            'motivo_anulacion'   => $this->motivo_anulacion,

            'venta' => $this->whenLoaded('venta', fn () => [
                'id'              => $this->venta->id,
                'serie'           => $this->venta->serie,
                'numero'          => $this->venta->numero,
                'tipo_documento'  => $this->venta->tipo_documento,
                'cliente' => $this->venta->cliente ? [
                    'id'               => $this->venta->cliente->id,
                    'razon_social'     => $this->venta->cliente->razon_social
                                            ?? trim("{$this->venta->cliente->nombres} {$this->venta->cliente->apellidos}"),
                    'telefono'         => $this->venta->cliente->telefono,
                    'numero_documento' => $this->venta->cliente->numero_documento,
                    'direccion'        => $this->venta->cliente->direccion,
                    // Direcciones con GPS — el modal Mapa de Entrega las usa para
                    // centrar el mapa sin geocodificar cuando la entrega no tiene coords.
                    'direcciones'      => $this->venta->cliente->relationLoaded('direcciones')
                        ? $this->venta->cliente->direcciones->map(fn ($d) => [
                            'tipo'      => $d->tipo,
                            'direccion' => $d->direccion,
                            'latitud'   => $d->latitud !== null ? (float) $d->latitud : null,
                            'longitud'  => $d->longitud !== null ? (float) $d->longitud : null,
                        ])
                        : [],
                ] : null,
            ]),

            'detalles' => EntregaDetalleItemResource::collection(
                $this->whenLoaded('detalles')
            ),
        ];
    }

    private function formatDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'toDateString')
            ? $value->toDateString()
            : substr((string) $value, 0, 10);
    }
}
