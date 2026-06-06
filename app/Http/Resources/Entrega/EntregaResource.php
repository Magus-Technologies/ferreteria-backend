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
        $chofer = $this->chofer;
        $vehiculo = $this->vehiculo;
        $fechaProgramada = $this->fecha_programada;
        $latitud = $this->latitud;
        $longitud = $this->longitud;

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
            'user_creador_id'   => $this->user_creador_id,
            'almacen_salida'    => $this->whenLoaded('almacenSalida', fn () => [
                'id'   => $this->almacenSalida->id,
                'name' => $this->almacenSalida->name,
            ]),
            'chofer_id' => $this->chofer_id,
            'chofer'    => $chofer ? [
                'id'   => $chofer->id,
                'name' => $chofer->name,
            ] : null,
            'vehiculo_id' => $this->vehiculo_id,
            'vehiculo'    => $vehiculo ? [
                'id'    => $vehiculo->id,
                'name'  => $vehiculo->name,
                'tipo'  => $vehiculo->tipo,
                'placa' => $vehiculo->placa,
            ] : null,

            // Pedido
            'tipo_pedido'   => $this->tipo_pedido,
            'cargo_destino' => $this->cargo_destino,

            // Fechas
            'fecha_creacion'   => $this->fecha_creacion?->toDateString(),
            'fecha_programada' => $this->formatDate($fechaProgramada),
            'fecha_ejecutada'  => $this->fecha_ejecutada?->toIso8601String(),
            'fecha_anulacion'  => $this->fecha_anulacion?->toIso8601String(),
            'hora_inicio'      => $this->hora_inicio,
            'hora_fin'         => $this->hora_fin,

            // Ubicación
            'direccion_entrega'  => $this->direccion_entrega,
            'referencia_entrega' => $this->referencia_entrega,
            'latitud'            => $latitud !== null ? (float) $latitud : null,
            'longitud'           => $longitud !== null ? (float) $longitud : null,

            // Notas
            'observaciones'   => $this->observaciones,
            'motivo_anulacion'=> $this->motivo_anulacion,

            // Venta resumida
            'venta' => $this->whenLoaded('venta', fn () => [
                'id'              => $venta->id,
                'serie'           => $venta->serie,
                'numero'          => $venta->numero,
                'tipo_documento'  => $venta->tipo_documento,
                'cliente'=> $cliente ? [
                    'id'               => $cliente->id,
                    'nombres'          => $cliente->nombres,
                    'apellidos'        => $cliente->apellidos,
                    'razon_social'     => $cliente->razon_social,
                    'telefono'         => $cliente->telefono,
                    'numero_documento' => $cliente->numero_documento,
                    'email'            => $cliente->email,
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
