<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class AperturarCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caja_principal_id' => 'required|integer|exists:cajas_principales,id',
            'monto_apertura' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'caja_principal_id.required' => 'La caja principal es requerida',
            'caja_principal_id.exists' => 'La caja principal no existe',
            'monto_apertura.required' => 'El monto de apertura es requerido',
            'monto_apertura.numeric' => 'El monto debe ser un número',
            'monto_apertura.min' => 'El monto debe ser mayor o igual a 0',
        ];
    }
}
