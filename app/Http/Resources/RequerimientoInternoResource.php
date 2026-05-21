<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequerimientoInternoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'titulo' => $this->titulo,
            'cargo' => $this->cargo,
            'assigned_cargo_id' => $this->assigned_cargo_id,
            'approval_state' => $this->approval_state,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'fecha_requerida' => $this->fecha_requerida?->format('Y-m-d'),
            'prioridad' => $this->prioridad,
            'tipo_solicitud' => $this->tipo_solicitud,
            'observaciones' => $this->observaciones,
            'duracion_cantidad' => $this->duracion_cantidad,
            'duracion_unidad' => $this->duracion_unidad,
            'vehiculo_id' => $this->vehiculo_id,
            'afecta_calendario' => $this->afecta_calendario,
            'estado' => $this->estado,
            'estado_solicitud' => $this->calcularEstadoSolicitud(),
            'proveedor_sugerido_id' => $this->proveedor_sugerido_id,
            'proveedor_sugerido' => $this->whenLoaded('proveedorSugerido', function () {
                return [
                    'id' => $this->proveedorSugerido->id,
                    'razon_social' => $this->proveedorSugerido->razon_social,
                    'ruc' => $this->proveedorSugerido->ruc,
                ];
            }),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'productos' => $this->whenLoaded('productos', function () {
                return $this->productos->map(function ($prod) {
                    return [
                        'id' => $prod->id,
                        'requerimiento_id' => $prod->requerimiento_id,
                        'producto_id' => $prod->producto_id,
                        'nombre_adicional' => $prod->nombre_adicional,
                        'cantidad' => (float) $prod->cantidad,
                        'cantidad_pendiente' => (float) ($prod->cantidad_pendiente ?? $prod->cantidad),
                        'cantidad_ordenada' => (float) ($prod->cantidad_ordenada ?? 0),
                        'cantidad_restante' => (float) $prod->cantidad_restante,
                        'estado_producto' => $prod->estado_producto,
                        'orden_compra_codigo' => $prod->orden_compra_codigo,
                        'orden_compra_id' => $prod->orden_compra_id,
                        'unidad' => $prod->unidad,
                        'producto' => $prod->producto ? [
                            'id' => $prod->producto->id,
                            'cod_producto' => $prod->producto->cod_producto,
                            'name' => $prod->producto->name,
                            'marca' => $prod->producto->marca ? [
                                'id' => $prod->producto->marca->id,
                                'name' => $prod->producto->marca->name,
                            ] : null,
                            'unidad_medida' => $prod->producto->unidadMedida ? [
                                'id' => $prod->producto->unidadMedida->id,
                                'name' => $prod->producto->unidadMedida->name,
                            ] : null,
                        ] : null,
                    ];
                });
            }),
            'servicios' => $this->whenLoaded('servicios', function () {
                return $this->servicios->map(function ($srv) {
                    return [
                        'id' => $srv->id,
                        'tipo_servicio' => $srv->tipo_servicio,
                        'descripcion_servicio' => $srv->descripcion_servicio,
                        'lugar_ejecucion' => $srv->lugar_ejecucion,
                        'fecha_inicio_estimada' => $srv->fecha_inicio_estimada?->format('Y-m-d H:i'),
                        'presupuesto_referencial' => (float) $srv->presupuesto_referencial,
                        'detalles' => $srv->detalles,
                        'duracion_cantidad' => $srv->duracion_cantidad,
                        'duracion_unidad' => $srv->duracion_unidad,
                    ];
                });
            }),
            'items_count' => $this->whenLoaded('productos', fn() => $this->productos->count()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function calcularEstadoSolicitud(): string
    {
        if (!$this->relationLoaded('productos') || $this->productos->isEmpty()) {
            return 'pendiente';
        }

        $totalProductos = $this->productos->count();
        $productosCompletos = $this->productos->filter(function ($p) {
            $cantidadRestante = (float) ($p->cantidad_pendiente ?? $p->cantidad) - (float) ($p->cantidad_ordenada ?? 0);
            return $cantidadRestante <= 0;
        })->count();

        if ($productosCompletos === 0) {
            return 'pendiente';
        }

        if ($productosCompletos === $totalProductos) {
            return 'aprobado';
        }

        return 'en_proceso';
    }
}
