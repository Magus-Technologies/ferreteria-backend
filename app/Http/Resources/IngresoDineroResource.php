<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngresoDineroResource extends JsonResource
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
            'fecha' => $this->fecha ?? $this->createdAt?->format('d/m/Y'),
            'hora' => $this->hora ?? $this->createdAt?->format('H:i:s'),
            'monto' => (float) $this->monto,
            'concepto' => $this->concepto ?? $this->extractConcepto(),
            'comentario' => $this->comentario ?? $this->extractComentario(),
            'cajero' => $this->cajero ?? $this->user?->name ?? 'SISTEMA',
            'autoriza' => $this->autoriza ?? $this->user?->name ?? 'SISTEMA',
            'anulado' => (bool) ($this->anulado ?? !($this->estado ?? true)),
            'tipo_origen' => $this->tipo_origen ?? 'Ingreso Directo',
            'metodo_pago' => $this->metodo_pago ?? $this->despliegueDePago?->name ?? 'N/A',
            'fecha_ordenamiento' => $this->fecha_ordenamiento ?? $this->createdAt,
            
            // Campos adicionales para compatibilidad
            'despliegue_de_pago' => $this->when(
                isset($this->despliegueDePago) && is_object($this->despliegueDePago),
                function () {
                    return [
                        'id' => $this->despliegueDePago->id,
                        'name' => $this->despliegueDePago->name,
                        'metodo_de_pago' => $this->despliegueDePago->metodoDePago?->name,
                    ];
                }
            ),
            
            'user' => $this->when(
                isset($this->user) && is_object($this->user),
                function () {
                    return [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                    ];
                }
            ),
        ];
    }

    /**
     * Extraer concepto de observaciones
     */
    private function extractConcepto(): string
    {
        if (!$this->observaciones) {
            return 'Ingreso';
        }

        // Si tiene el formato "concepto - comentario"
        if (str_contains($this->observaciones, ' - ')) {
            return explode(' - ', $this->observaciones)[0];
        }

        return $this->observaciones;
    }

    /**
     * Extraer comentario de observaciones
     */
    private function extractComentario(): string
    {
        if (!$this->observaciones) {
            return '';
        }

        // Si tiene el formato "concepto - comentario"
        if (str_contains($this->observaciones, ' - ')) {
            $parts = explode(' - ', $this->observaciones, 2);
            return $parts[1] ?? '';
        }

        return '';
    }
}