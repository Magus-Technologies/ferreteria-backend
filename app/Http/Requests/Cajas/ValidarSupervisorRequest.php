<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class ValidarSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supervisor_id' => ['required', 'exists:user,id'],
            'supervisor_password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'supervisor_id.required' => 'El id del supervisor es requerido',
            'supervisor_id.exists' => 'El supervisor no existe en el sistema',
            'supervisor_password.required' => 'La contraseña de supervisor es requerida',
        ];
    }
}
