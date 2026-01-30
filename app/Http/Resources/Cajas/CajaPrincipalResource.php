<?php

namespace App\Http\Resources\Cajas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CajaPrincipalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'numero_documento' => $this->user->numero_documento,
            ],
            'sub_cajas' => SubCajaResource::collection($this->whenLoaded('subCajas')),
            'total_sub_cajas' => $this->subCajas->count(),
            'saldo_total' => $this->subCajas->sum('saldo_actual'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
