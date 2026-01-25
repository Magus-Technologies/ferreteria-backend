<?php

namespace App\Http\Resources\Producto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for single Product representation
 *
 * Used for: show(), store(), update() responses
 */
class ProductoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cod_producto' => $this->cod_producto,
            'cod_barra' => $this->cod_barra,
            'name' => $this->name,
            'name_ticket' => $this->name_ticket,
            'accion_tecnica' => $this->accion_tecnica,
            'img' => $this->img,
            'img_url' => $this->img ? asset('storage/' . $this->img) : null,
            'ficha_tecnica' => $this->ficha_tecnica,
            'ficha_tecnica_url' => $this->ficha_tecnica ? asset('storage/' . $this->ficha_tecnica) : null,
            'stock_min' => $this->stock_min,
            'stock_max' => $this->stock_max,
            'unidades_contenidas' => $this->unidades_contenidas,
            'estado' => $this->estado,
            'permitido' => $this->permitido,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relations
            'categoria' => $this->whenLoaded('categoria', fn() => [
                'id' => $this->categoria->id,
                'name' => $this->categoria->name,
            ]),
            'marca' => $this->whenLoaded('marca', fn() => [
                'id' => $this->marca->id,
                'name' => $this->marca->name,
            ]),
            'unidad_medida' => $this->whenLoaded('unidadMedida', fn() => [
                'id' => $this->unidadMedida->id,
                'name' => $this->unidadMedida->name,
            ]),

            // Warehouse specific data
            'almacenes' => $this->whenLoaded('productoEnAlmacenes', fn() =>
                $this->productoEnAlmacenes->map(fn($pa) => [
                    'id' => $pa->id,
                    'almacen_id' => $pa->almacen_id,
                    'almacen' => $pa->relationLoaded('almacen') ? [
                        'id' => $pa->almacen->id,
                        'name' => $pa->almacen->name,
                    ] : null,
                    'ubicacion' => $pa->relationLoaded('ubicacion') && $pa->ubicacion ? [
                        'id' => $pa->ubicacion->id,
                        'name' => $pa->ubicacion->name,
                    ] : null,
                    'costo' => (float) $pa->costo,
                    'stock_fraccion' => (float) $pa->stock_fraccion,
                    'unidades_derivadas' => $pa->relationLoaded('unidadesDerivadas')
                        ? ProductoPrecioResource::collection($pa->unidadesDerivadas)
                        : [],
                ])
            ),

            // Computed fields
            'tiene_ingresos' => $this->when(isset($this->tiene_ingresos), $this->tiene_ingresos),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
            ],
        ];
    }
}
