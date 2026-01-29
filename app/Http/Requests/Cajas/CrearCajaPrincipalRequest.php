<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class CrearCajaPrincipalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|string|exists:user,id',
            'nombre' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'El vendedor es requerido',
            'user_id.exists' => 'El vendedor no existe',
            'nombre.required' => 'El nombre de la caja es requerido',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
        ];
    }
}
