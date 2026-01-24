<?php

namespace App\Http\Resources\Producto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Product Price (Unidad Derivada) representation
 */
class ProductoPrecioResource extends JsonResource
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
            'producto_almacen_id' => $this->producto_almacen_id,
            'unidad_derivada_id' => $this->unidad_derivada_id,
            'unidad_derivada' => $this->whenLoaded('unidadDerivada', fn() => [
                'id' => $this->unidadDerivada->id,
                'name' => $this->unidadDerivada->name,
            ]),
            'factor' => (float) $this->factor,

            // Precios
            'precio_publico' => (float) $this->precio_publico,
            'comision_publico' => (float) ($this->comision_publico ?? 0),

            'precio_especial' => (float) ($this->precio_especial ?? 0),
            'comision_especial' => (float) ($this->comision_especial ?? 0),
            'activador_especial' => $this->activador_especial,

            'precio_minimo' => (float) ($this->precio_minimo ?? 0),
            'comision_minimo' => (float) ($this->comision_minimo ?? 0),
            'activador_minimo' => $this->activador_minimo,

            'precio_ultimo' => $this->precio_ultimo ? (float) $this->precio_ultimo : null,
            'comision_ultimo' => (float) ($this->comision_ultimo ?? 0),
            'activador_ultimo' => $this->activador_ultimo,

            // Computed margins (if costo is available in context)
            'margen_publico' => $this->when(
                isset($this->costo) && $this->costo > 0,
                fn() => round((($this->precio_publico - $this->costo) / $this->costo) * 100, 2)
            ),
        ];
    }
}
