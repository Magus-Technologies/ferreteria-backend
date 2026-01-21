<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarTransaccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sub_caja_id' => 'required|integer|exists:sub_cajas,id',
            'tipo_transaccion' => 'required|string|in:ingreso,egreso',
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:500',
            'despliegue_pago_id' => 'nullable|string|exists:despliegue_de_pago,id',
            'referencia_id' => 'nullable|string|max:191',
            'referencia_tipo' => 'nullable|string|max:50',
            'conteo_billetes_monedas' => 'nullable|array',
            'conteo_billetes_monedas.billete_200' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.billete_100' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.billete_50' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.billete_20' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.billete_10' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.moneda_5' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.moneda_2' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.moneda_1' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.moneda_050' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.moneda_020' => 'nullable|integer|min:0',
            'conteo_billetes_monedas.moneda_010' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'sub_caja_id.required' => 'La sub-caja es requerida',
            'sub_caja_id.exists' => 'La sub-caja no existe',
            'tipo_transaccion.required' => 'El tipo de transacción es requerido',
            'tipo_transaccion.in' => 'Tipo de transacción inválido. Valores permitidos: ingreso, egreso',
            'monto.required' => 'El monto es requerido',
            'monto.numeric' => 'El monto debe ser un número',
            'monto.min' => 'El monto debe ser mayor a 0',
            'descripcion.required' => 'La descripción es requerida',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres',
            'despliegue_pago_id.exists' => 'El método de pago no existe',
            'referencia_id.max' => 'La referencia ID no puede exceder 191 caracteres',
            'referencia_tipo.max' => 'El tipo de referencia no puede exceder 50 caracteres',
        ];
    }
}
