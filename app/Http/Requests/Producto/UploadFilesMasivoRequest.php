<?php

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;

class UploadFilesMasivoRequest extends FormRequest
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
            'files' => 'required|array|min:1|max:50',
            'files.*' => 'required|file|max:10240', // 10MB per file
            'tipo' => 'required|in:img,ficha_tecnica',
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
            'files.required' => 'Debe proporcionar al menos un archivo',
            'files.array' => 'Los archivos deben enviarse como un arreglo',
            'files.min' => 'Debe proporcionar al menos un archivo',
            'files.max' => 'No puede subir más de 50 archivos a la vez',
            'files.*.required' => 'Cada elemento debe ser un archivo válido',
            'files.*.file' => 'Cada elemento debe ser un archivo válido',
            'files.*.max' => 'Cada archivo no puede superar los 10MB',
            'tipo.required' => 'El tipo de archivo es requerido',
            'tipo.in' => 'El tipo debe ser: img o ficha_tecnica',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'files' => 'archivos',
            'tipo' => 'tipo de archivo',
        ];
    }
}
