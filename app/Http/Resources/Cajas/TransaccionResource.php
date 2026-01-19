<?php

namespace App\Http\Resources\Cajas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransaccionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_transaccion' => $this->tipo_transaccion,
            'tipo_transaccion_label' => $this->getTipoTransaccionLabel(),
            'monto' => number_format($this->monto, 2, '.', ''),
            'saldo_anterior' => number_format($this->saldo_anterior, 2, '.', ''),
            'saldo_nuevo' => number_format($this->saldo_nuevo, 2, '.', ''),
            'descripcion' => $this->descripcion,
            'referencia_id' => $this->referencia_id,
            'referencia_tipo' => $this->referencia_tipo,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'fecha' => $this->fecha->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    private function getTipoTransaccionLabel(): string
    {
        return match($this->tipo_transaccion) {
            'ingreso' => 'Ingreso',
            'egreso' => 'Egreso',
            'prestamo_enviado' => 'Préstamo Enviado',
            'prestamo_recibido' => 'Préstamo Recibido',
            'movimiento_interno_salida' => 'Movimiento Interno - Salida',
            'movimiento_interno_entrada' => 'Movimiento Interno - Entrada',
            default => $this->tipo_transaccion,
        };
    }
}
