<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdenCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'requerimiento_id' => $this->requerimiento_id,
            'requerimiento' => $this->whenLoaded('requerimiento', function () {
                return [
                    'id' => $this->requerimiento->id,
                    'codigo' => $this->requerimiento->codigo,
                    'titulo' => $this->requerimiento->titulo,
                    'area' => $this->requerimiento->area,
                    'prioridad' => $this->requerimiento->prioridad,
                ];
            }),
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => $this->whenLoaded('proveedor', function () {
                return [
                    'id' => $this->proveedor->id,
                    'razon_social' => $this->proveedor->razon_social,
                    'ruc' => $this->proveedor->ruc,
                ];
            }),
            'fecha' => $this->fecha?->format('Y-m-d'),
            'tipo_moneda' => $this->tipo_moneda,
            'tipo_de_cambio' => (float) $this->tipo_de_cambio,
            'ruc' => $this->ruc,
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'numero' => $this->numero,
            'guia' => $this->guia,
            'percepcion' => (float) $this->percepcion,
            'forma_de_pago' => $this->forma_de_pago,
            'numero_dias' => $this->numero_dias,
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'estado' => $this->estado?->value ?? $this->estado,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'almacen_id' => $this->almacen_id,
            'productos' => $this->whenLoaded('productos', function () {
                return $this->productos->map(function ($prod) {
                    return [
                        'id' => $prod->id,
                        'producto_id' => $prod->producto_id,
                        'codigo' => $prod->codigo,
                        'nombre' => $prod->nombre,
                        'marca' => $prod->marca,
                        'unidad' => $prod->unidad,
                        'cantidad' => (float) $prod->cantidad,
                        'precio' => (float) $prod->precio,
                        'subtotal' => (float) $prod->subtotal,
                        'flete' => (float) $prod->flete,
                        'vencimiento' => $prod->vencimiento?->format('Y-m-d'),
                        'lote' => $prod->lote,
                    ];
                });
            }),
            'productos_count' => $this->when(isset($this->productos_count), $this->productos_count),
            'total' => $this->when(isset($this->total), (float) ($this->total ?? 0)),
            'total_flete' => $this->when(isset($this->total_flete), (float) ($this->total_flete ?? 0)),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
