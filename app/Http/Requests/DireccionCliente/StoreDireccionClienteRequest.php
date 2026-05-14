<?php

namespace App\Http\Requests\DireccionCliente;

use Illuminate\Foundation\Http\FormRequest;

class StoreDireccionClienteRequest extends FormRequest
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
            'direccion' => 'nullable|string|max:500',
            'referencia' => 'nullable|string|max:500',
            'latitud' => [
                'nullable',
                'numeric',
                'between:-90,90',
                function ($attribute, $value, $fail) {
                    // Si latitud está presente, longitud debe estar presente
                    if ($value !== null && $this->input('longitud') === null) {
                        $fail('Si proporciona latitud, debe proporcionar también la longitud.');
                    }
                },
            ],
            'longitud' => [
                'nullable',
                'numeric',
                'between:-180,180',
                function ($attribute, $value, $fail) {
                    // Si longitud está presente, latitud debe estar presente
                    if ($value !== null && $this->input('latitud') === null) {
                        $fail('Si proporciona longitud, debe proporcionar también la latitud.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'direccion.required' => 'El campo dirección es requerido',
            'direccion.max' => 'La dirección no puede exceder 500 caracteres',
            'latitud.numeric' => 'La latitud debe ser un valor numérico',
            'latitud.between' => 'La latitud debe estar entre -90 y 90 grados',
            'longitud.numeric' => 'La longitud debe ser un valor numérico',
            'longitud.between' => 'La longitud debe estar entre -180 y 180 grados',
        ];
    }
}
