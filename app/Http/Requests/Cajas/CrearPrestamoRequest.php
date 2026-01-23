<?php

namespace App\Http\Requests\Cajas;

use App\Models\DespliegueDePago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CrearPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caja_principal_origen_id' => ['required', 'integer', 'exists:cajas_principales,id'],
            'sub_caja_origen_id' => ['nullable', 'integer', 'exists:sub_cajas,id'], // Ahora opcional
            'sub_caja_destino_id' => ['required', 'integer', 'exists:sub_cajas,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'despliegue_de_pago_id' => ['nullable', 'string', 'exists:desplieguedepago,id'],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'caja_principal_origen_id.required' => 'La caja principal origen es requerida',
            'caja_principal_origen_id.exists' => 'La caja principal origen no existe',
            'sub_caja_origen_id.exists' => 'La sub-caja origen no existe',
            'sub_caja_destino_id.required' => 'La sub-caja destino es requerida',
            'sub_caja_destino_id.exists' => 'La sub-caja destino no existe',
            'monto.required' => 'El monto es requerido',
            'monto.min' => 'El monto debe ser mayor a 0',
            'despliegue_de_pago_id.exists' => 'El método de pago no existe',
        ];
    }

    /**
     * Configurar el validador con reglas adicionales
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarSoloEfectivo($validator);
        });
    }

    /**
     * Validar que los préstamos solo sean de efectivo
     */
    private function validarSoloEfectivo(Validator $validator): void
    {
        $desplieguePagoId = $this->input('despliegue_de_pago_id');

        // Si no hay método de pago especificado, no validar
        if (!$desplieguePagoId) {
            return;
        }

        // Obtener el método de pago
        $desplieguePago = DespliegueDePago::with('metodoDePago')->find($desplieguePagoId);
        if (!$desplieguePago || !$desplieguePago->metodoDePago) {
            return;
        }

        // Verificar que sea efectivo
        $esEfectivo = strtolower($desplieguePago->metodoDePago->nombre) === 'efectivo';
        
        if (!$esEfectivo) {
            $validator->errors()->add(
                'despliegue_de_pago_id',
                'Los préstamos entre cajas solo pueden ser de efectivo. ' .
                'No se permiten préstamos de ' . $desplieguePago->metodoDePago->nombre . '. ' .
                'Para mover dinero de otros métodos de pago, usa movimientos internos.'
            );
        }
    }
}
