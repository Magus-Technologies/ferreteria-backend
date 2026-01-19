<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class CrearMovimientoInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sub_caja_origen_id' => ['required', 'integer', 'exists:sub_cajas,id'],
            'sub_caja_destino_id' => ['required', 'integer', 'exists:sub_cajas,id', 'different:sub_caja_origen_id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'despliegue_de_pago_id' => ['nullable', 'string', 'exists:desplieguedepago,id'],
            'justificacion' => ['required', 'string', 'max:1000'],
            'comprobante' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'sub_caja_origen_id.required' => 'La sub-caja origen es requerida',
            'sub_caja_origen_id.exists' => 'La sub-caja origen no existe',
            'sub_caja_destino_id.required' => 'La sub-caja destino es requerida',
            'sub_caja_destino_id.exists' => 'La sub-caja destino no existe',
            'sub_caja_destino_id.different' => 'La sub-caja destino debe ser diferente a la origen',
            'monto.required' => 'El monto es requerido',
            'monto.min' => 'El monto debe ser mayor a 0',
            'justificacion.required' => 'La justificación es requerida',
            'justificacion.max' => 'La justificación no puede exceder 1000 caracteres',
        ];
    }
}
