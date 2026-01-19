<?php

namespace App\Http\Resources\Cajas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesplieguePagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'adicional' => number_format($this->adicional, 2, '.', ''),
            'mostrar' => $this->mostrar,
            'metodo_de_pago' => [
                'id' => $this->metodoDePago->id,
                'name' => $this->metodoDePago->name,
                'cuenta_bancaria' => $this->metodoDePago->cuenta_bancaria,
                'monto' => number_format($this->metodoDePago->monto, 2, '.', ''),
            ],
        ];
    }
}
