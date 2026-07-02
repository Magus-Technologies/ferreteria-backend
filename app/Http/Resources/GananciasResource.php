<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class GananciasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? uniqid(),
            'fecha' => $this->fecha,
            'hora_emision' => $this->hora_emision ?? null,
            'fecha_vencimiento' => $this->fecha_vencimiento ?? null,
            'tipo_doc' => $this->mapearTipoDocumento($this->tipo_doc),
            'numero' => $this->numero,
            'f_pago' => $this->mapearFormaPago($this->f_pago),
            'cliente' => $this->cliente,
            'vendedor' => $this->vendedor,
            'producto' => $this->producto,
            'marca' => $this->marca,
            'unidad' => $this->unidad,
            'cant' => (float) $this->cant,
            'p_unit' => (float) $this->p_unit,
            'subtot' => (float) $this->subtot,
            'costo' => (float) $this->costo,
            'costo_total' => (float) $this->costo_total,
            'ganancia' => (float) $this->ganancia,
            'cc' => $this->cc,
            // Indicador de desglose por lote PEPS (ej. "Lote 1/2"); null si la
            // fila no proviene de un desglose por costos distintos.
            'desglose_lote' => $this->desglose_lote ?? null,
            // Serie-número de la compra de origen del costo (PEPS). "N compras" si
            // el lote se surtió de varias; null si no hay registro de consumo.
            'documento_pagado' => $this->documento_pagado ?? null,
            // Impacto de tipo de cambio (costo a TC compra − costo al TC realmente
            // pagado) y fecha del pago; solo para lotes de compras en dólares con pago
            // registrado. null si no aplica (soles, sin pago, u origen no es compra).
            'impacto_tc' => isset($this->impacto_tc) ? (float) $this->impacto_tc : null,
            'fecha_pago_compra' => $this->fecha_pago_compra ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Formatear fecha para mostrar
     */
    private function formatearFecha($fecha): string
    {
        if (!$fecha) return '';
        
        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Exception $e) {
            return $fecha;
        }
    }

    /**
     * Mapear tipo de documento a abreviación
     */
    private function mapearTipoDocumento($tipoDocumento): string
    {
        if (!$tipoDocumento) return '';
        
        $mapeo = [
            'nv' => 'NV',
            'Nota de Venta' => 'NV',
            '03' => 'BOL',
            'Boleta' => 'BOL',
            '01' => 'FAC', 
            'Factura' => 'FAC',
            'Ticket' => 'TKT',
        ];

        return $mapeo[$tipoDocumento] ?? strtoupper($tipoDocumento);
    }

    /**
     * Mapear forma de pago
     */
    private function mapearFormaPago($formaPago): string
    {
        if (!$formaPago) return '';
        
        $mapeo = [
            'co' => 'CO',
            'Contado' => 'CO',
            'cr' => 'CR',
            'Credito' => 'CR',
            'Transferencia' => 'TRANSFER',
            'Tarjeta' => 'TARJETA',
        ];

        return $mapeo[$formaPago] ?? strtoupper($formaPago);
    }
}