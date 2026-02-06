<?php

namespace App\Http\Resources\FacturacionElectronica;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaCreditoResource extends JsonResource
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
            'motivo_id' => $this->motivo_id,
            'descripcion' => $this->descripcion,
            'monto_total' => $this->monto_total,
            'monto_igv' => $this->monto_igv,
            'monto_subtotal' => $this->monto_subtotal,
            'referencia_documento' => $this->referencia_documento,
            'fecha' => $this->fecha?->format('Y-m-d H:i:s'),
            'estado' => $this->estado,
            'almacen_id' => $this->almacen_id,
            'usuario_id' => $this->usuario_id,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            'venta' => $this->whenLoaded('venta', function () {
                return [
                    'id' => $this->venta->id,
                    'serie' => $this->venta->serie,
                    'numero' => $this->venta->numero,
                    'numero_completo' => "{$this->venta->serie}-{$this->venta->numero}",
                    'tipo_documento' => $this->venta->tipo_documento,
                    'monto_total' => $this->venta->monto_total,
                ];
            }),
            
            'motivo' => $this->whenLoaded('motivo', function () {
                return [
                    'id' => $this->motivo->id,
                    'codigo' => $this->motivo->codigo,
                    'descripcion' => $this->motivo->descripcion,
                    'tipo' => $this->motivo->tipo,
                ];
            }),
            
            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => $this->usuario->id,
                    'name' => $this->usuario->name,
                    'email' => $this->usuario->email,
                ];
            }),
            
            'almacen' => $this->whenLoaded('almacen', function () {
                return [
                    'id' => $this->almacen->id,
                    'nombre' => $this->almacen->nombre,
                ];
            }),
            
            'comprobante_electronico' => $this->whenLoaded('comprobanteElectronico', function () {
                return $this->comprobanteElectronico ? [
                    'id' => $this->comprobanteElectronico->id,
                    'estado_sunat' => $this->comprobanteElectronico->estado_sunat,
                    'codigo_sunat' => $this->comprobanteElectronico->codigo_sunat,
                    'mensaje_sunat' => $this->comprobanteElectronico->mensaje_sunat,
                    'fecha_envio_sunat' => $this->comprobanteElectronico->fecha_envio_sunat?->format('Y-m-d H:i:s'),
                    'tiene_xml' => !empty($this->comprobanteElectronico->xml_path),
                    'tiene_cdr' => !empty($this->comprobanteElectronico->cdr_path),
                ] : null;
            }),
            
            'puede_editarse' => $this->puedeEditarse(),
            'puede_enviarse' => $this->puedeEnviarse(),
            'puede_cancelarse' => $this->puedeCancelarse(),
        ];
    }
}
