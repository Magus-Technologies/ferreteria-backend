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
            'fecha' => $this->fecha, // Ya viene formateada desde el query
            'hora_emision' => $this->hora_emision ?? null, // Nueva columna de hora
            'tipo_doc' => $this->mapearTipoDocumento($this->tipo_doc),
            'numero' => $this->numero,
            'f_pago' => $this->mapearFormaPago($this->f_pago),
            'cliente' => $this->cliente,
            'vendedor' => $this->vendedor,
            'producto' => $this->producto,
            'marca' => $this->marca,
            'cant' => (float) $this->cant,
            'p_unit' => (float) $this->p_unit,
            'subtot' => (float) $this->subtot,
            'costo_unit' => (float) $this->costo_unit,
            'costo_total' => (float) $this->costo_total,
            'ganancia' => (float) $this->ganancia,
            'cc' => $this->cc,
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