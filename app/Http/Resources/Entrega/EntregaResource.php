<?php

namespace App\Http\Resources\Entrega;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Shape completo — usado en respuestas de acciones (confirmar, anular, etc.) */
class EntregaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $venta    = $this->whenLoaded('venta');
        $cliente  = optional($venta)->client ?? optional($venta)->cliente;

        return [
            'id'                      => $this->id,
            'venta_id'                => $this->venta_id,
            'venta_entrega_secuencia' => $this->venta_entrega_secuencia,
            'stock_aplicado'          => (bool) $this->stock_aplicado,

            // Catálogos
            'tipo_entrega'  => $this->whenLoaded('tipoEntrega', fn () => [
                'id'     => $this->tipoEntrega->id,
                'codigo' => $this->tipoEntrega->codigo->value,
                'nombre' => $this->tipoEntrega->nombre,
                'icono'  => $this->tipoEntrega->icono,
                'color'  => $this->tipoEntrega->color,
            ]),
            'tipo_despacho' => $this->whenLoaded('tipoDespacho', fn () => $this->tipoDespacho ? [
                'id'     => $this->tipoDespacho->id,
                'codigo' => $this->tipoDespacho->codigo->value,
                'nombre' => $this->tipoDespacho->nombre,
            ] : null),
            'estado_entrega'=> $this->whenLoaded('estadoEntrega', fn () => [
                'id'       => $this->estadoEntrega->id,
                'codigo'   => $this->estadoEntrega->codigo->value,
                'nombre'   => $this->estadoEntrega->nombre,
                'color'    => $this->estadoEntrega->color,
                'es_final' => (bool) $this->estadoEntrega->es_final,
            ]),
            'quien_entrega' => $this->whenLoaded('quienEntrega', fn () => $this->quienEntrega ? [
                'id'     => $this->quienEntrega->id,
                'codigo' => $this->quienEntrega->codigo->value,
                'nombre' => $this->quienEntrega->nombre,
            ] : null),

            // Logística
            'almacen_salida_id' => $this->almacen_salida_id,
            'almacen_salida'    => $this->whenLoaded('almacenSalida', fn () => [
                'id'   => $this->almacenSalida->id,
                'name' => $this->almacenSalida->name,
            ]),
            'chofer_id' => $this->chofer_id,
            'chofer'    => $this->whenLoaded('chofer', fn () => $this->chofer ? [
                'id'   => $this->chofer->id,
                'name' => $this->chofer->name,
            ] : null),
            'vehiculo_id' => $this->vehiculo_id,
            'vehiculo'    => $this->whenLoaded('vehiculo', fn () => $this->vehiculo ? [
                'id'    => $this->vehiculo->id,
                'name'  => $this->vehiculo->name,
                'tipo'  => $this->vehiculo->tipo,
                'placa' => $this->vehiculo->placa,
            ] : null),

            // Pedido
            'tipo_pedido'   => $this->tipo_pedido,
            'cargo_destino' => $this->cargo_destino,

            // Fechas
            'fecha_creacion'   => $this->fecha_creacion?->toDateString(),
            'fecha_programada' => $this->fecha_programada?->toDateString(),
            'fecha_ejecutada'  => $this->fecha_ejecutada?->toIso8601String(),
            'fecha_anulacion'  => $this->fecha_anulacion?->toIso8601String(),
            'hora_inicio'      => $this->hora_inicio,
            'hora_fin'         => $this->hora_fin,

            // Ubicación
            'direccion_entrega'  => $this->direccion_entrega,
            'referencia_entrega' => $this->referencia_entrega,
            'latitud'            => $this->latitud ? (float) $this->latitud : null,
            'longitud'           => $this->longitud ? (float) $this->longitud : null,

            // Notas
            'observaciones'   => $this->observaciones,
            'motivo_anulacion'=> $this->motivo_anulacion,

            // Venta resumida
            'venta' => $this->whenLoaded('venta', fn () => [
                'id'     => $venta->id,
                'serie'  => $venta->serie,
                'numero' => $venta->numero,
                'cliente'=> $cliente ? [
                    'id'              => $cliente->id,
                    'nombres'         => $cliente->nombres,
                    'apellidos'       => $cliente->apellidos,
                    'razon_social'    => $cliente->razon_social,
                    'telefono'        => $cliente->telefono,
                ] : null,
            ]),

            // Detalles (productos)
            'detalles' => EntregaDetalleItemResource::collection(
                $this->whenLoaded('detalles')
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
