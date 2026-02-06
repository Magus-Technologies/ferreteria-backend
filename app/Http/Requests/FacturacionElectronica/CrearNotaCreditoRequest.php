<?php

namespace App\Http\Requests\FacturacionElectronica;

use Illuminate\Foundation\Http\FormRequest;

class CrearNotaCreditoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'venta_id' => 'required|string|exists:venta,id',
            'motivo_id' => 'required|integer|exists:motivo_nota,id',
            'serie' => 'required|string|max:10',
            'numero' => 'nullable|integer|min:1',
            'almacen_id' => 'required|integer|exists:almacen,id',
            'descripcion' => 'required|string|max:500',
            'monto_total' => 'required|numeric|min:0',
            'monto_igv' => 'required|numeric|min:0',
            'monto_subtotal' => 'required|numeric|min:0',
            'fecha' => 'nullable|date',
            'observaciones' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.codigo' => 'required_with:items|string',
            'items.*.descripcion' => 'required_with:items|string',
            'items.*.cantidad' => 'required_with:items|numeric|min:0',
            'items.*.valor_venta' => 'required_with:items|numeric|min:0',
            'items.*.igv' => 'required_with:items|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'venta_id.required' => 'La venta es requerida',
            'venta_id.exists' => 'La venta no existe',
            'motivo_id.required' => 'El motivo es requerido',
            'motivo_id.exists' => 'El motivo no existe',
            'serie.required' => 'La serie es requerida',
            'almacen_id.required' => 'El almacén es requerido',
            'descripcion.required' => 'La descripción es requerida',
            'monto_total.required' => 'El monto total es requerido',
            'monto_igv.required' => 'El IGV es requerido',
            'monto_subtotal.required' => 'El subtotal es requerido',
        ];
    }
}
