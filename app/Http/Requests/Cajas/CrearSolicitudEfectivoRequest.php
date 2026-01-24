<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class CrearSolicitudEfectivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'apertura_cierre_caja_id' => ['required', 'string', 'exists:apertura_cierre_caja,id'],
            'vendedor_prestamista_id' => ['required', 'integer', 'exists:users,id'],
            'monto_solicitado' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'apertura_cierre_caja_id.required' => 'La apertura de caja es requerida',
            'apertura_cierre_caja_id.exists' => 'La apertura de caja no existe',
            'vendedor_prestamista_id.required' => 'El vendedor prestamista es requerido',
            'vendedor_prestamista_id.exists' => 'El vendedor no existe',
            'monto_solicitado.required' => 'El monto es requerido',
            'monto_solicitado.min' => 'El monto debe ser mayor a 0',
            'motivo.max' => 'El motivo no puede exceder 500 caracteres',
        ];
    }
}
