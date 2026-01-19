<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class CrearPrestamoRequest extends FormRequest
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
            'motivo' => ['nullable', 'string', 'max:1000'],
            'user_recibe_id' => ['required', 'string', 'exists:user,id'],
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
            'user_recibe_id.required' => 'El usuario que recibe es requerido',
            'user_recibe_id.exists' => 'El usuario que recibe no existe',
        ];
    }
}
