<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraResource extends JsonResource
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
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'numero' => $this->numero,
            'descripcion' => $this->descripcion,
            'forma_de_pago' => $this->forma_de_pago,
            'tipo_moneda' => $this->tipo_moneda,
            'tipo_de_cambio' => $this->tipo_de_cambio,
            'percepcion' => $this->percepcion,
            'numero_dias' => $this->numero_dias,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'fecha' => $this->fecha,
            'guia' => $this->guia,
            'estado_de_compra' => $this->estado_de_compra,
            'egreso_dinero_id' => $this->egreso_dinero_id,
            'despliegue_de_pago_id' => $this->despliegue_de_pago_id,
            'user_id' => $this->user_id,
            'almacen_id' => $this->almacen_id,
            'proveedor_id' => $this->proveedor_id,
            'orden_compra_id' => $this->orden_compra_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'proveedor' => $this->whenLoaded('proveedor', function () {
                return [
                    'id' => $this->proveedor->id,
                    'ruc' => $this->proveedor->ruc,
                    'razon_social' => $this->proveedor->razon_social,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'productos_por_almacen' => $this->productosPorAlmacen ?? [],
            'orden_compra' => $this->when($this->relationLoaded('ordenCompra'), function () {
                if ($this->ordenCompra) {
                    return [
                        'id' => $this->ordenCompra->id,
                        'codigo' => $this->ordenCompra->codigo,
                        'estado' => $this->ordenCompra->estado ?? null,
                    ];
                }
                return null;
            }),
            'recepciones_almacen_count' => $this->recepciones_almacen_count ?? 0,
            'pagos_de_compras_count' => $this->pagos_de_compras_count ?? 0,
            'total_pagado' => $this->total_pagado ?? 0,
            // Saldo en la moneda de la compra (dólares si tipo_moneda = Dólares) y estado de cuenta.
            // esta_pagado: true = pagado, false = por pagar, null = no aplica (anulada/no calculado)
            'saldo_pendiente' => $this->saldo_pendiente ?? null,
            'esta_pagado' => $this->esta_pagado,
            'ultima_fecha_pago_referencial' => $this->ultima_fecha_pago_referencial ?? null,
        ];
    }
}
