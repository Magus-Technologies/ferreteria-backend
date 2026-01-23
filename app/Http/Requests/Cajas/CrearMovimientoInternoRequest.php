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
            'sub_caja_destino_id' => ['required', 'integer', 'exists:sub_cajas,id', 'different:sub_caja_origen_id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'despliegue_de_pago_id' => ['nullable', 'string', 'exists:desplieguedepago,id'],
            'justificacion' => ['required', 'string', 'max:1000'],
            'comprobante' => ['nullable', 'string', 'max:255'],
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
            $this->validarCompatibilidadMetodoPago($validator);
        });
    }

    /**
     * Validar que no se transfiera dinero no-efectivo a una caja que no lo acepta
     */
    private function validarCompatibilidadMetodoPago(Validator $validator): void
    {
        $desplieguePagoId = $this->input('despliegue_de_pago_id');
        $subCajaDestinoId = $this->input('sub_caja_destino_id');

        // Si no hay método de pago especificado, no validar
        if (!$desplieguePagoId || !$subCajaDestinoId) {
            return;
        }

        // Obtener el método de pago
        $desplieguePago = DespliegueDePago::with('metodoDePago')->find($desplieguePagoId);
        if (!$desplieguePago || !$desplieguePago->metodoDePago) {
            return;
        }

        // Obtener la sub-caja destino
        $subCajaDestino = SubCaja::find($subCajaDestinoId);
        if (!$subCajaDestino) {
            return;
        }

        // Verificar si el método de pago NO es efectivo
        $esEfectivo = strtolower($desplieguePago->metodoDePago->nombre) === 'efectivo';
        
        // Si el método de pago NO es efectivo, verificar que la caja destino lo acepte
        if (!$esEfectivo) {
            // Verificar si la caja destino acepta este método de pago
            $aceptaMetodo = $subCajaDestino->aceptaMetodoPago($desplieguePagoId);
            
            if (!$aceptaMetodo) {
                $validator->errors()->add(
                    'despliegue_de_pago_id',
                    'No se puede transferir dinero de ' . $desplieguePago->metodoDePago->nombre . 
                    ' a la caja "' . $subCajaDestino->nombre . '" porque no acepta este método de pago. ' .
                    'Solo puedes transferir efectivo a esta caja o elegir una caja destino que acepte ' . 
                    $desplieguePago->metodoDePago->nombre . '.'
                );
            }
        }
    }
}
