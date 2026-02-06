<?php

namespace App\Http\Resources\FacturacionElectronica;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaDebitoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_documento' => $this->tipo_documento,
            'serie' => $this->serie,
            'numero' => $this->numero,
            'numero_completo' => $this->numero_completo,
            'venta_id' => $this->venta_id,
            'venta' => $this->whenLoaded('venta', function () {
                return [
                    'id' => $this->venta->id,
                    'serie' => $this->venta->serie,
                    'numero' => $this->venta->numero,
                    'numero_completo' => "{$this->venta->serie}-{$this->venta->numero}",
                    'total' => $this->venta->total,
                ];
            }),
            'motivo_id' => $this->motivo_id,
            'motivo' => $this->whenLoaded('motivo', function () {
                return [
                    'id' => $this->motivo->id,
                    'codigo' => $this->motivo->codigo,
                    'descripcion' => $this->motivo->descripcion,
                    'tipo' => $this->motivo->tipo,
                ];
            }),
            'descripcion' => $this->descripcion,
            'monto_total' => (float) $this->monto_total,
            'monto_igv' => (float) $this->monto_igv,
            'monto_subtotal' => (float) $this->monto_subtotal,
            'referencia_documento' => $this->referencia_documento,
            'fecha' => $this->fecha?->format('Y-m-d H:i:s'),
            'fecha_formato' => $this->fecha?->format('d/m/Y'),
            'estado' => $this->estado,
            'usuario_id' => $this->usuario_id,
            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => $this->usuario->id,
                    'name' => $this->usuario->name,
                    'email' => $this->usuario->email,
                ];
            }),
            'almacen_id' => $this->almacen_id,
            'almacen' => $this->whenLoaded('almacen', function () {
                return [
                    'id' => $this->almacen->id,
                    'nombre' => $this->almacen->nombre,
                ];
            }),
            'observaciones' => $this->observaciones,
            'comprobante_electronico' => $this->whenLoaded('comprobanteElectronico', function () {
                return new ComprobanteElectronicoResource($this->comprobanteElectronico);
            }),
            'puede_editarse' => $this->puedeEditarse(),
            'puede_enviarse' => $this->puedeEnviarse(),
            'puede_cancelarse' => $this->puedeCancelarse(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
