<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearIngresoRequest extends FormRequest
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
            'concepto' => 'required|string|max:255',
            'comentario' => 'sometimes|nullable|string|max:500',
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
            'concepto.required' => 'El concepto es obligatorio.',
            'concepto.max' => 'El concepto no puede tener más de 255 caracteres.',
            'comentario.max' => 'El comentario no puede tener más de 500 caracteres.',
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
            'concepto' => 'concepto',
            'comentario' => 'comentario',
            'despliegue_de_pago_id' => 'método de pago',
            'autoriza' => 'autoriza',
        ];
    }
}