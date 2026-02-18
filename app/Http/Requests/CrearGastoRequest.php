<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearGastoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'monto' => 'required|numeric|min:0.01|max:999999.99',
            'motivo' => 'required|string|max:255',
            'destino' => 'sometimes|nullable|string|max:255',
            'comprobante' => 'sometimes|nullable|string|max:100',
            'vuelto' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'despliegue_de_pago_id' => 'required|string|exists:desplieguedepago,id',
            'autoriza' => 'sometimes|nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un número válido.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'monto.max' => 'El monto no puede ser mayor a 999,999.99.',
            'motivo.required' => 'El motivo es obligatorio.',
            'motivo.max' => 'El motivo no puede tener más de 255 caracteres.',
            'destino.max' => 'El destino no puede tener más de 255 caracteres.',
            'comprobante.max' => 'El comprobante no puede tener más de 100 caracteres.',
            'vuelto.numeric' => 'El vuelto debe ser un número válido.',
            'vuelto.min' => 'El vuelto no puede ser negativo.',
            'vuelto.max' => 'El vuelto no puede ser mayor a 999,999.99.',
            'despliegue_de_pago_id.required' => 'El método de pago es obligatorio.',
            'despliegue_de_pago_id.exists' => 'El método de pago seleccionado no es válido.',
            'autoriza.max' => 'El campo autoriza no puede tener más de 255 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'monto' => 'monto',
            'motivo' => 'motivo',
            'destino' => 'destino',
            'comprobante' => 'comprobante',
            'vuelto' => 'vuelto',
            'despliegue_de_pago_id' => 'método de pago',
            'autoriza' => 'autoriza',
        ];
    }
}