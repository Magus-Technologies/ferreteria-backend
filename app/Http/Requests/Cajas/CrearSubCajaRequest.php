<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class CrearSubCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caja_principal_id' => 'required|integer|exists:cajas_principales,id',
            'nombre' => 'required|string|max:255',
            'despliegues_pago_ids' => 'required|array|min:1',
            'despliegues_pago_ids.*' => 'required|string',
            'tipos_comprobante' => 'required|array|min:1',
            'tipos_comprobante.*' => 'required|string|in:01,03,nv',
            'proposito' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'caja_principal_id.required' => 'La caja principal es requerida',
            'caja_principal_id.exists' => 'La caja principal no existe',
            'nombre.required' => 'El nombre de la sub-caja es requerido',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'despliegues_pago_ids.required' => 'Debe seleccionar al menos un método de pago',
            'despliegues_pago_ids.array' => 'Los métodos de pago deben ser un array',
            'despliegues_pago_ids.min' => 'Debe seleccionar al menos un método de pago',
            'tipos_comprobante.required' => 'Debe seleccionar al menos un tipo de comprobante',
            'tipos_comprobante.array' => 'Los tipos de comprobante deben ser un array',
            'tipos_comprobante.min' => 'Debe seleccionar al menos un tipo de comprobante',
            'tipos_comprobante.*.in' => 'Tipo de comprobante inválido. Valores permitidos: 01 (Factura), 03 (Boleta), nv (Nota de Venta)',
            'proposito.max' => 'El propósito no puede exceder 500 caracteres',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $desplieguePagoIds = $this->input('despliegues_pago_ids', []);
            
            // Si es ["*"], no validar IDs individuales
            if (in_array('*', $desplieguePagoIds)) {
                // Validar que si usa "*", no tenga otros IDs
                if (count($desplieguePagoIds) > 1) {
                    $validator->errors()->add('despliegues_pago_ids', 'Si selecciona "*" (todos los métodos), no puede seleccionar otros métodos específicos.');
                }
                return;
            }

            // Validar que los IDs existan en desplieguedepago
            $existentes = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)->pluck('id')->toArray();
            $noExistentes = array_diff($desplieguePagoIds, $existentes);
            
            if (!empty($noExistentes)) {
                $validator->errors()->add('despliegues_pago_ids', 'Algunos métodos de pago no existen: ' . implode(', ', $noExistentes));
            }
        });
    }
}
