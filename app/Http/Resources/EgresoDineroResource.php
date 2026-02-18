<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EgresoDineroResource extends JsonResource
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
            'destino' => $this->destino ?? $this->extractDestino(),
            'motivo' => $this->motivo ?? $this->extractMotivo(),
            'comprobante' => $this->comprobante ?? 'EFRAIN',
            'cajero' => $this->cajero ?? $this->user?->name ?? 'SISTEMA',
            'autoriza' => $this->autoriza ?? $this->user?->name ?? 'SISTEMA',
            'anulado' => (bool) ($this->anulado ?? !($this->estado ?? true)),
            'tipo_origen' => $this->tipo_origen ?? 'Gasto Directo',
            'metodo_pago' => $this->metodo_pago ?? $this->despliegueDePago?->name ?? 'N/A',
            'fecha_ordenamiento' => $this->fecha_ordenamiento ?? $this->createdAt,
            'vuelto' => (float) ($this->vuelto ?? 0),
            
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
     * Extraer motivo de observaciones
     */
    private function extractMotivo(): string
    {
        if (!$this->observaciones) {
            return 'Gasto';
        }

        // Si tiene el formato "motivo - Destino: destino"
        if (str_contains($this->observaciones, ' - Destino: ')) {
            return explode(' - Destino: ', $this->observaciones)[0];
        }

        // Si tiene el formato "motivo - comentario"
        if (str_contains($this->observaciones, ' - ')) {
            return explode(' - ', $this->observaciones)[0];
        }

        return $this->observaciones;
    }

    /**
     * Extraer destino de observaciones
     */
    private function extractDestino(): string
    {
        if (!$this->observaciones) {
            return 'VARIOS';
        }

        // Si tiene el formato "motivo - Destino: destino"
        if (str_contains($this->observaciones, ' - Destino: ')) {
            $parts = explode(' - Destino: ', $this->observaciones, 2);
            if (isset($parts[1])) {
                // Extraer solo el destino, sin otros comentarios adicionales
                return explode(' - ', $parts[1])[0];
            }
        }

        return 'VARIOS';
    }
}