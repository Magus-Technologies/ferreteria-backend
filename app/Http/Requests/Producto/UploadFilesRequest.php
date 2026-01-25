<?php

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;

class UploadFilesRequest extends FormRequest
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
            'img' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB
            'ficha_tecnica' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB
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
            'img.file' => 'La imagen debe ser un archivo',
            'img.mimes' => 'La imagen debe ser de tipo: jpg, jpeg, png, gif o webp',
            'img.max' => 'La imagen no puede superar los 5MB',
            'ficha_tecnica.file' => 'La ficha técnica debe ser un archivo',
            'ficha_tecnica.mimes' => 'La ficha técnica debe ser de tipo: pdf, doc o docx',
            'ficha_tecnica.max' => 'La ficha técnica no puede superar los 10MB',
        ];
    }
}
