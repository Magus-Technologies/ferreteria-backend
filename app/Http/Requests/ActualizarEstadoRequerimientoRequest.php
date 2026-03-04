<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarEstadoRequerimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'in:pendiente,aprobado,rechazado,anulado'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es requerido',
            'estado.in' => 'El estado debe ser: pendiente, aprobado, rechazado o anulado',
        ];
    }
}
