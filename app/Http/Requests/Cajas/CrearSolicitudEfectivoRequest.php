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
            'apertura_cierre_caja_id' => ['required', 'string'],
            'vendedor_prestamista_id' => ['required', 'string'],
            'monto_solicitado' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que la apertura existe
            if ($this->apertura_cierre_caja_id) {
                $aperturaExists = \App\Models\AperturaCierreCaja::where('id', $this->apertura_cierre_caja_id)->exists();
                if (!$aperturaExists) {
                    $validator->errors()->add('apertura_cierre_caja_id', 'La apertura de caja no existe');
                }
            }
            
            // Validar que el vendedor existe
            if ($this->vendedor_prestamista_id) {
                $vendedorExists = \App\Models\User::where('id', $this->vendedor_prestamista_id)->exists();
                if (!$vendedorExists) {
                    $validator->errors()->add('vendedor_prestamista_id', 'El vendedor no existe');
                }
            }
        });
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
