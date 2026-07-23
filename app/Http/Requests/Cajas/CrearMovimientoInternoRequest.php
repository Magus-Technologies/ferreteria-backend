<?php

namespace App\Http\Requests\Cajas;

use App\Models\DespliegueDePago;
use App\Models\SubCaja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CrearMovimientoInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sub_caja_origen_id' => ['required', 'integer', 'exists:sub_cajas,id'],
            // Puede ser la MISMA sub-caja cuando el destino es el efectivo de un
            // usuario (destino_user_id): traslado de dinero cerrado → sesión.
            // La regla se valida en withValidator.
            'sub_caja_destino_id' => ['required', 'integer', 'exists:sub_cajas,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            // Opcionales: el modal simple de "Movimiento Interno entre Sub-Cajas"
            // usa un CONCEPTO (etiqueta de solo nombre) en lugar de despliegues.
            'despliegue_de_pago_origen_id' => ['nullable', 'string', 'exists:desplieguedepago,id'],
            'despliegue_de_pago_destino_id' => ['nullable', 'string', 'exists:desplieguedepago,id'],
            'concepto' => ['nullable', 'string', 'max:255'],
            // Usuario al que se le ACREDITA el dinero en el destino (Traslado de
            // Efectivo apuntando al efectivo de un usuario específico)
            'destino_user_id' => ['nullable', 'string', 'exists:user,id'],
            'justificacion' => ['required', 'string', 'max:1000'],
            'comprobante' => ['nullable', 'string', 'max:255'],
            'numero_operacion' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'sub_caja_origen_id.required' => 'La sub-caja origen es requerida',
            'sub_caja_origen_id.exists' => 'La sub-caja origen no existe',
            'sub_caja_destino_id.required' => 'La sub-caja destino es requerida',
            'sub_caja_destino_id.exists' => 'La sub-caja destino no existe',
            'sub_caja_destino_id.different' => 'La sub-caja destino debe ser diferente a la origen',
            'monto.required' => 'El monto es requerido',
            'monto.min' => 'El monto debe ser mayor a 0',
            'despliegue_de_pago_origen_id.required' => 'El método de pago origen es requerido',
            'despliegue_de_pago_destino_id.required' => 'El método de pago destino es requerido',
            'justificacion.required' => 'La justificación es requerida',
            'justificacion.max' => 'La justificación no puede exceder 1000 caracteres',
        ];
    }

    /**
     * Configurar el validador con reglas adicionales
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Misma sub-caja solo se permite cuando se acredita al efectivo de
            // un usuario específico (destino_user_id)
            if ($this->input('sub_caja_origen_id') == $this->input('sub_caja_destino_id')
                && !$this->input('destino_user_id')) {
                $validator->errors()->add(
                    'sub_caja_destino_id',
                    'La sub-caja destino debe ser diferente a la origen (salvo que el destino sea el efectivo de un usuario)'
                );
            }

            $this->validarCompatibilidadMetodoPago($validator);
        });
    }

    /**
     * Validar que no se transfiera dinero no-efectivo a una caja que no lo acepta
     */
    private function validarCompatibilidadMetodoPago(Validator $validator): void
    {
        $desplieguePagoOrigenId = $this->input('despliegue_de_pago_origen_id');
        $desplieguePagoDestinoId = $this->input('despliegue_de_pago_destino_id');
        $subCajaOrigenId = $this->input('sub_caja_origen_id');
        $subCajaDestinoId = $this->input('sub_caja_destino_id');

        if (!$desplieguePagoOrigenId || !$desplieguePagoDestinoId || !$subCajaOrigenId || !$subCajaDestinoId) {
            return;
        }

        // Obtener los métodos de pago
        $desplieguePagoOrigen = DespliegueDePago::with('metodoDePago')->find($desplieguePagoOrigenId);
        $desplieguePagoDestino = DespliegueDePago::with('metodoDePago')->find($desplieguePagoDestinoId);
        
        if (!$desplieguePagoOrigen || !$desplieguePagoDestino) {
            return;
        }

        // Obtener las sub-cajas
        $subCajaOrigen = SubCaja::find($subCajaOrigenId);
        $subCajaDestino = SubCaja::find($subCajaDestinoId);
        
        if (!$subCajaOrigen || !$subCajaDestino) {
            return;
        }

        // Validar que la sub-caja origen tenga el método de pago origen
        $tieneMetodoOrigen = $subCajaOrigen->aceptaMetodoPago($desplieguePagoOrigenId);
        if (!$tieneMetodoOrigen) {
            $validator->errors()->add(
                'despliegue_de_pago_origen_id',
                'La sub-caja origen "' . $subCajaOrigen->nombre . '" no tiene el método de pago ' . 
                $desplieguePagoOrigen->name
            );
        }

        // Validar que la sub-caja destino acepte el método de pago destino
        $aceptaMetodoDestino = $subCajaDestino->aceptaMetodoPago($desplieguePagoDestinoId);
        if (!$aceptaMetodoDestino) {
            $validator->errors()->add(
                'despliegue_de_pago_destino_id',
                'La sub-caja destino "' . $subCajaDestino->nombre . '" no acepta el método de pago ' . 
                $desplieguePagoDestino->name
            );
        }
    }
}
