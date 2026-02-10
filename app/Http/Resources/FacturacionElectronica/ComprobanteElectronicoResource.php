<?php

namespace App\Http\Resources\FacturacionElectronica;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComprobanteElectronicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'venta_id' => $this->venta_id,
            'tipo_comprobante' => $this->tipo_comprobante,
            'tipo_comprobante_nombre' => $this->getTipoComprobanteName(),
            'serie' => $this->serie,
            'numero' => $this->correlativo,
            'correlativo' => $this->correlativo,
            'serie_numero' => "{$this->serie}-{$this->correlativo}",
            'fecha_emision' => $this->fecha_emision,
            'cliente_id' => $this->cliente_id,
            'cliente_razon_social' => $this->cliente_razon_social,
            'cliente_numero_documento' => $this->cliente_numero_documento,
            'moneda' => $this->moneda,
            'tipo_moneda' => $this->moneda,
            'subtotal' => $this->operacion_gravada,
            'operacion_gravada' => $this->operacion_gravada,
            'igv' => $this->total_igv,
            'total_igv' => $this->total_igv,
            'total' => $this->importe_total,
            'importe_total' => $this->importe_total,
            'estado_sunat' => $this->estado_sunat,
            'xml_path' => $this->xml_path,
            'xml_firmado' => $this->xml_firmado,
            'cdr_path' => $this->cdr_path,
            'pdf_path' => $this->pdf_path,
            'hash' => $this->hash_cpe,
            'hash_cpe' => $this->hash_cpe,
            
            // Cliente
            'cliente' => $this->when($this->relationLoaded('cliente'), function () {
                $cliente = $this->cliente;
                
                // Construir el nombre completo
                $nombre = '';
                if ($cliente->razon_social) {
                    $nombre = $cliente->razon_social;
                } else {
                    $nombres = $cliente->nombres ?? '';
                    $apellidos = $cliente->apellidos ?? '';
                    $nombre = trim("{$nombres} {$apellidos}");
                }
                
                return [
                    'id' => $cliente->id,
                    'tipo_cliente' => $cliente->tipo_cliente,
                    'tipo_documento' => $cliente->tipo_cliente === 'JURIDICO' ? '6' : '1',
                    'numero_documento' => $cliente->numero_documento,
                    'nombres' => $cliente->nombres,
                    'apellidos' => $cliente->apellidos,
                    'razon_social' => $cliente->razon_social,
                    'nombre' => $nombre ?: 'Sin nombre',
                    'direccion' => $cliente->direccion,
                    'telefono' => $cliente->telefono,
                    'email' => $cliente->email,
                ];
            }),
            
            // Detalles
            'detalles' => $this->when($this->relationLoaded('detalles'), function () {
                return $this->detalles->map(function ($detalle) {
                    return [
                        'id' => $detalle->id,
                        'codigo_producto' => $detalle->codigo_producto, // ✅ Usar campo directo de la tabla
                        'descripcion' => $detalle->descripcion,
                        'unidad_medida' => $detalle->unidad_medida, // ✅ Usar campo directo de la tabla
                        'cantidad' => (float) $detalle->cantidad,
                        'precio_unitario' => (float) $detalle->precio_unitario,
                        'subtotal' => (float) $detalle->valor_venta,
                        'igv' => (float) $detalle->igv,
                        'total' => (float) ($detalle->valor_venta + $detalle->igv),
                        'tipo_moneda' => $this->moneda,
                    ];
                });
            }),
            
            // Venta relationship (only if loaded)
            'venta' => $this->when($this->relationLoaded('venta'), function () {
                return $this->venta ? [
                    'id' => $this->venta->id,
                    'serie' => $this->venta->serie,
                    'numero' => $this->venta->numero,
                ] : null;
            }),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    
    private function getTipoComprobanteName(): string
    {
        return match($this->tipo_comprobante) {
            '01' => 'Factura',
            '03' => 'Boleta',
            '07' => 'Nota de Crédito',
            '08' => 'Nota de Débito',
            default => 'Desconocido',
        };
    }
}
