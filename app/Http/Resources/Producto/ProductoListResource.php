<?php

namespace App\Http\Resources\Producto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Product List representation (optimized for listings)
 *
 * Used for: index() response - lighter than ProductoResource
 */
class ProductoListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $almacenId = $request->input('almacen_id');
        $productoAlmacen = $this->whenLoaded('productoEnAlmacenes', function () use ($almacenId) {
            return $this->productoEnAlmacenes->first();
        });

        return [
            'id' => $this->id,
            'cod_producto' => $this->cod_producto,
            'cod_barra' => $this->cod_barra,
            'name' => $this->name,
            'name_ticket' => $this->name_ticket,
            'estado' => $this->estado,
            'img' => $this->img,
            'img_url' => $this->img ? asset('storage/' . $this->img) : null,
            'stock_min' => $this->stock_min,
            'unidades_contenidas' => $this->unidades_contenidas,

            // Relations (compact)
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

            // Warehouse data (single warehouse from filter)
            'producto_almacen' => $this->whenLoaded('productoEnAlmacenes', function () {
                $pa = $this->productoEnAlmacenes->first();
                if (!$pa) return null;

                return [
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
                    'stock_bajo' => $pa->stock_fraccion <= $this->stock_min,
                    'unidades_derivadas' => $pa->relationLoaded('unidadesDerivadas')
                        ? $pa->unidadesDerivadas->map(fn($ud) => [
                            'id' => $ud->id,
                            'unidad_derivada_id' => $ud->unidad_derivada_id,
                            'unidad_derivada' => $ud->relationLoaded('unidadDerivada') ? [
                                'id' => $ud->unidadDerivada->id,
                                'name' => $ud->unidadDerivada->name,
                            ] : null,
                            'factor' => (float) $ud->factor,
                            'precio_publico' => (float) $ud->precio_publico,
                            'comision_publico' => (float) ($ud->comision_publico ?? 0),
                        ])
                        : [],
                    'compras' => $pa->relationLoaded('compras')
                        ? $pa->compras->map(fn($c) => [
                            'id' => $c->id,
                            'compra_id' => $c->compra_id,
                            'compra' => $c->relationLoaded('compra') ? [
                                'id' => $c->compra->id,
                                'fecha' => $c->compra->fecha,
                                'proveedor' => $c->compra->relationLoaded('proveedor') && $c->compra->proveedor
                                    ? $c->compra->proveedor->razon_social
                                    : null,
                            ] : null,
                        ])
                        : [],
                ];
            }),

            // Computed
            'tiene_ingresos' => $this->when(isset($this->tiene_ingresos), $this->tiene_ingresos),
        ];
    }
}
