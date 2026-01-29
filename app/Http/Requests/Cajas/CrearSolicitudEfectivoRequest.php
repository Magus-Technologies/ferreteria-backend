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
            'apertura_cierre_caja_id' => ['nullable', 'string'], // Ahora es opcional
            'vendedor_prestamista_id' => ['required', 'string'],
            'monto_solicitado' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Si no se proporciona apertura_cierre_caja_id, buscar la apertura activa del prestamista
            if (!$this->apertura_cierre_caja_id && $this->vendedor_prestamista_id) {
                // Buscar la caja principal del prestamista
                $cajaPrincipal = \App\Models\CajaPrincipal::where('user_id', $this->vendedor_prestamista_id)->first();
                
                if ($cajaPrincipal) {
                    // Buscar la apertura activa
                    $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $cajaPrincipal->id)
                        ->whereNull('fecha_cierre')
                        ->first();
                    
                    if ($aperturaActiva) {
                        // Asignar la apertura encontrada
                        $this->merge(['apertura_cierre_caja_id' => $aperturaActiva->id]);
                    } else {
                        $validator->errors()->add('apertura_cierre_caja_id', 'El vendedor prestamista no tiene una caja abierta');
                    }
                } else {
                    $validator->errors()->add('vendedor_prestamista_id', 'El vendedor prestamista no tiene una caja asignada');
                }
            }
            
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
