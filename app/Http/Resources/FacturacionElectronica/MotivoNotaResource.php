<?php

namespace App\Http\Resources\FacturacionElectronica;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MotivoNotaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo,
            'activo' => $this->activo,
            'es_nota_debito' => $this->esNotaDebito(),
            'es_nota_credito' => $this->esNotaCredito(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
