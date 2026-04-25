<?php

namespace App\Http\Resources\FacturacionElectronica;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaDebitoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Obtener comprobante electrónico manualmente
        $comprobanteElectronico = \App\Models\ComprobanteElectronico::where('serie', $this->serie)
            ->where('correlativo', $this->numero)
            ->where('tipo_comprobante', '08')
            ->first();

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
                    'codigo_sunat' => $this->motivo->codigo_sunat ?? $this->motivo->codigo,
                    'descripcion' => $this->motivo->descripcion,
                    'tipo' => $this->motivo->tipo,
                ];
            }),
            'descripcion' => $this->descripcion,
            'monto_total' => (float) $this->monto_total,
            'total' => (float) $this->monto_total, // Alias para compatibilidad con frontend
            'monto_igv' => (float) $this->monto_igv,
            'monto_subtotal' => (float) $this->monto_subtotal,
            'referencia_documento' => $this->referencia_documento,
            'fecha' => $this->fecha,
            'fecha_formato' => $this->fecha?->format('d/m/Y'),
            'estado' => $this->estado,
            'estado_sunat' => $comprobanteElectronico?->estado_sunat ?? 'pendiente',
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
            'comprobante_electronico' => $comprobanteElectronico ? [
                'id' => $comprobanteElectronico->id,
                'tipo_comprobante' => $comprobanteElectronico->tipo_comprobante,
                'serie' => $comprobanteElectronico->serie,
                'correlativo' => $comprobanteElectronico->correlativo,
                'estado_sunat' => $comprobanteElectronico->estado_sunat,
                'tiene_xml' => !empty($comprobanteElectronico->xml_path),
                'tiene_cdr' => !empty($comprobanteElectronico->cdr_path),
                'xml_path' => $comprobanteElectronico->xml_path,
                'cdr_path' => $comprobanteElectronico->cdr_path,
            ] : null,
            'puede_editarse' => $this->puedeEditarse(),
            'puede_enviarse' => $this->puedeEnviarse(),
            'puede_cancelarse' => $this->puedeCancelarse(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
