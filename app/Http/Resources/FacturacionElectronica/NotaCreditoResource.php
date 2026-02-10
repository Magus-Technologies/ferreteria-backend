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
            'fecha_emision' => $this->fecha?->format('Y-m-d H:i:s'),
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
                    'cliente' => $this->venta->cliente ? [
                        'id' => $this->venta->cliente->id,
                        'tipo_cliente' => $this->venta->cliente->tipo_cliente,
                        'tipo_documento' => $this->venta->cliente->tipo_documento,
                        'numero_documento' => $this->venta->cliente->numero_documento,
                        'nombres' => $this->venta->cliente->nombres,
                        'apellidos' => $this->venta->cliente->apellidos,
                        'razon_social' => $this->venta->cliente->razon_social,
                        'nombre' => $this->venta->cliente->nombre,
                        'direccion' => $this->venta->cliente->direccion,
                        'telefono' => $this->venta->cliente->telefono,
                        'email' => $this->venta->cliente->email,
                    ] : null,
                ];
            }),
            
            'motivo_nota' => $this->whenLoaded('motivo', function () {
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
                    'empresa' => $this->usuario->empresa ? [
                        'id' => $this->usuario->empresa->id,
                        'ruc' => $this->usuario->empresa->ruc,
                        'razon_social' => $this->usuario->empresa->razon_social,
                        'direccion' => $this->usuario->empresa->direccion,
                        'distrito' => $this->usuario->empresa->distrito,
                        'celular' => $this->usuario->empresa->celular,
                        'email' => $this->usuario->empresa->email,
                    ] : null,
                ];
            }),
            
            'almacen' => $this->whenLoaded('almacen', function () {
                return [
                    'id' => $this->almacen->id,
                    'name' => $this->almacen->nombre,
                ];
            }),
            
            // ✅ Comprobante electrónico de la NOTA DE CRÉDITO (no el comprobante afectado)
            'comprobante_electronico' => $this->comprobanteElectronico ? [
                'id' => $this->comprobanteElectronico->id,
                'tipo_comprobante' => $this->comprobanteElectronico->tipo_comprobante,
                'serie' => $this->comprobanteElectronico->serie,
                'correlativo' => $this->comprobanteElectronico->correlativo,
                'numero' => $this->comprobanteElectronico->numero_completo,
                'estado_sunat' => $this->comprobanteElectronico->estado_sunat,
                'fecha_emision' => $this->comprobanteElectronico->fecha_emision?->format('Y-m-d'),
                'tiene_xml' => !empty($this->comprobanteElectronico->xml_firmado) || !empty($this->comprobanteElectronico->xml_path),
                'tiene_cdr' => !empty($this->comprobanteElectronico->cdr_xml) || !empty($this->comprobanteElectronico->cdr_path),
                'hash_cpe' => $this->comprobanteElectronico->hash_cpe,
            ] : null,
            
            // Comprobante que se está afectando (factura/boleta original)
            'comprobante_referencia' => $this->whenLoaded('comprobanteReferencia', function () {
                if (!$this->comprobanteReferencia) return null;
                
                $comprobante = $this->comprobanteReferencia;
                
                // ✅ Cargar detalles explícitamente si no están cargados
                if (!$comprobante->relationLoaded('detalles')) {
                    $comprobante->load('detalles');
                }
                
                return [
                    'id' => $comprobante->id,
                    'tipo_comprobante' => $comprobante->tipo_comprobante,
                    'serie' => $comprobante->serie,
                    'correlativo' => $comprobante->correlativo,
                    'numero' => $comprobante->numero_completo, // Serie-Correlativo formateado
                    'estado_sunat' => $comprobante->estado_sunat,
                    'fecha_emision' => $comprobante->fecha_emision?->format('Y-m-d'),
                    'tiene_xml' => !empty($comprobante->xml_firmado) || !empty($comprobante->xml_path),
                    'tiene_cdr' => !empty($comprobante->cdr_xml) || !empty($comprobante->cdr_path),
                    'cliente' => [
                        'tipo_documento' => $comprobante->cliente_tipo_documento,
                        'numero_documento' => $comprobante->cliente_numero_documento,
                        'razon_social' => $comprobante->cliente_razon_social,
                        'direccion' => $comprobante->cliente_direccion,
                        'email' => $comprobante->cliente_email,
                        'telefono' => $comprobante->cliente_telefono,
                    ],
                    'detalles' => $comprobante->detalles->map(function ($detalle) {
                        return [
                            'id' => $detalle->id,
                            'codigo_producto' => $detalle->codigo_producto,
                            'descripcion' => $detalle->descripcion,
                            'unidad_medida' => $detalle->unidad_medida,
                            'cantidad' => $detalle->cantidad,
                            'precio_unitario' => $detalle->precio_unitario,
                            'valor_venta' => $detalle->subtotal, // Valor sin IGV
                            'subtotal' => $detalle->subtotal,
                            'igv' => $detalle->igv,
                            'total' => $detalle->total,
                        ];
                    })->toArray(),
                ];
            }),
            
            'puede_editarse' => $this->puedeEditarse(),
            'puede_enviarse' => $this->puedeEnviarse(),
            'puede_cancelarse' => $this->puedeCancelarse(),
        ];
    }
}
