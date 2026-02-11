<?php

namespace App\Http\Resources\Cajas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesplieguePagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $banco = $this->metodoDePago->name ?? 'Sin Banco';
        $metodo = $this->name;
        $titular = $this->metodoDePago->nombre_titular ?? null;
        
        // Crear label específico: Titular/Banco/Método o Banco/Método si no hay titular
        $label = $titular 
            ? "{$titular}/{$banco}/{$metodo}"
            : "{$banco}/{$metodo}";
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $label, // Label completo para mostrar en selects
            'adicional' => number_format($this->adicional, 2, '.', ''),
            'mostrar' => $this->mostrar,
            'activo' => $this->activo,
            'requiere_numero_serie' => $this->requiere_numero_serie,
            'numero_celular' => $this->numero_celular,
            'sobrecargo_porcentaje' => number_format($this->sobrecargo_porcentaje ?? 0, 2, '.', ''),
            'tipo_sobrecargo' => $this->tipo_sobrecargo ?? 'ninguno',
            'metodo_de_pago_id' => $this->metodo_de_pago_id,
            'metodo_de_pago' => [
                'id' => $this->metodoDePago->id,
                'name' => $this->metodoDePago->name,
                'cuenta_bancaria' => $this->metodoDePago->cuenta_bancaria,
                'nombre_titular' => $this->metodoDePago->nombre_titular,
                'monto' => number_format($this->metodoDePago->monto, 2, '.', ''),
                'monto_inicial' => number_format($this->metodoDePago->monto_inicial ?? 0, 2, '.', ''),
            ],
        ];
    }
}
