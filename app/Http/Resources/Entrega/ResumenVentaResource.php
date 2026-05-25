<?php

namespace App\Http\Resources\Entrega;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Shape de fila en la tabla MAESTRA de mis-entregas */
class ResumenVentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clienteNombre = $this->razon_social
            ?? trim(($this->nombres ?? '') . ' ' . ($this->apellidos ?? ''))
            ?: 'SIN CLIENTE';

        return [
            'venta_id'        => $this->venta_id,
            'serie'           => $this->serie,
            'numero'          => $this->numero,
            'venta_numero'    => "{$this->serie}-{$this->numero}",
            'fecha'           => $this->fecha,

            'cliente_nombre'           => $clienteNombre,
            'cliente_numero_documento' => $this->numero_documento,
            'cliente_telefono'         => $this->telefono,

            'total_entregas'           => (int) ($this->total_entregas ?? 0),
            'completadas'              => (int) ($this->completadas ?? 0),
            'en_camino'                => (int) ($this->en_camino ?? 0),
            'pendientes'               => (int) ($this->pendientes ?? 0),
            'canceladas'               => (int) ($this->canceladas ?? 0),
            'proxima_fecha_programada' => $this->proxima_fecha_programada,
            'ultima_fecha_ejecutada'   => $this->ultima_fecha_ejecutada,
        ];
    }
}
