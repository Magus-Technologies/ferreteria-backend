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
            'tipo_documento' => $this->tipo_documento,
            'documento_id' => $this->documento_id,
            'serie' => $this->serie,
            'numero' => $this->numero,
            'numero_completo' => $this->numero_completo,
            'fecha_emision' => $this->fecha_emision?->format('Y-m-d H:i:s'),
            'fecha_envio_sunat' => $this->fecha_envio_sunat?->format('Y-m-d H:i:s'),
            'estado_sunat' => $this->estado_sunat,
            'codigo_sunat' => $this->codigo_sunat,
            'mensaje_sunat' => $this->mensaje_sunat,
            'tiene_xml' => $this->tieneXml(),
            'tiene_cdr' => $this->tieneCdr(),
            'hash_cpe' => $this->hash_cpe,
            'hash_cdr' => $this->hash_cdr,
            'numero_ticket_sunat' => $this->numero_ticket_sunat,
            'observaciones' => $this->observaciones,
            'fue_enviado' => $this->fueEnviado(),
            'esta_aceptado' => $this->estaAceptado(),
            'esta_rechazado' => $this->estaRechazado(),
            'esta_pendiente' => $this->estaPendiente(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
