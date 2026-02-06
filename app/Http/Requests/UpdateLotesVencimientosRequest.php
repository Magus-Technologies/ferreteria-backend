<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLotesVencimientosRequest extends FormRequest
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
            'unidades_derivadas' => 'required|array|min:1',
            'unidades_derivadas.*.id' => 'required|integer|exists:unidadderivadainmutablecompra,id',
            'unidades_derivadas.*.lote' => 'nullable|string|max:255',
            'unidades_derivadas.*.vencimiento' => 'nullable|date',
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
            'unidades_derivadas.required' => 'Debe proporcionar al menos una unidad derivada para actualizar',
            'unidades_derivadas.*.id.required' => 'El ID de la unidad derivada es requerido',
            'unidades_derivadas.*.id.exists' => 'La unidad derivada no existe',
            'unidades_derivadas.*.lote.max' => 'El lote no puede exceder 255 caracteres',
            'unidades_derivadas.*.vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida',
        ];
    }
}
