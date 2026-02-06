<?php

namespace App\Http\Requests\FacturacionElectronica;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarNotaCreditoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_id' => 'nullable|integer|exists:motivo_nota,id',
            'descripcion' => 'nullable|string|max:500',
            'monto_total' => 'nullable|numeric|min:0',
            'monto_igv' => 'nullable|numeric|min:0',
            'monto_subtotal' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.codigo' => 'required_with:items|string',
            'items.*.descripcion' => 'required_with:items|string',
            'items.*.cantidad' => 'required_with:items|numeric|min:0',
            'items.*.valor_venta' => 'required_with:items|numeric|min:0',
            'items.*.igv' => 'required_with:items|numeric|min:0',
        ];
    }
}
