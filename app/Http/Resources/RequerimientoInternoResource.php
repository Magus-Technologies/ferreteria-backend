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
            'area' => $this->area,
            'fecha_requerida' => $this->fecha_requerida?->format('Y-m-d'),
            'prioridad' => $this->prioridad,
            'tipo_solicitud' => $this->tipo_solicitud,
            'observaciones' => $this->observaciones,
            'estado' => $this->estado,
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
                        'producto_id' => $prod->producto_id,
                        'cantidad' => (float) $prod->cantidad,
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
            'servicio' => $this->whenLoaded('servicio', function () {
                if (!$this->servicio) return null;
                return [
                    'id' => $this->servicio->id,
                    'tipo_servicio' => $this->servicio->tipo_servicio,
                    'descripcion_servicio' => $this->servicio->descripcion_servicio,
                    'lugar_ejecucion' => $this->servicio->lugar_ejecucion,
                    'fecha_inicio_estimada' => $this->servicio->fecha_inicio_estimada?->format('Y-m-d'),
                    'presupuesto_referencial' => (float) $this->servicio->presupuesto_referencial,
                    'duracion_cantidad' => $this->servicio->duracion_cantidad,
                    'duracion_unidad' => $this->servicio->duracion_unidad,
                ];
            }),
            'items_count' => $this->whenLoaded('productos', fn() => $this->productos->count()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
