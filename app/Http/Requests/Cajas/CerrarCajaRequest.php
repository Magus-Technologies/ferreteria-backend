<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class CerrarCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_cierre_efectivo' => ['required', 'numeric', 'min:0'],
            'total_cuentas' => ['required', 'numeric', 'min:0'],
            'conteo_billetes_monedas' => ['nullable', 'array'],
            'conteo_billetes_monedas.billete_200' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.billete_100' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.billete_50' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.billete_20' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.billete_10' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.moneda_5' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.moneda_2' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.moneda_1' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.moneda_050' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.moneda_020' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.moneda_010' => ['nullable', 'integer', 'min:0'],
            'conceptos_adicionales' => ['nullable', 'array'],
            'conceptos_adicionales.*.concepto' => ['required', 'string', 'max:255'],
            'conceptos_adicionales.*.numero' => ['nullable', 'string', 'max:50'],
            'conceptos_adicionales.*.cantidad' => ['required', 'numeric', 'min:0'],
            'comentarios' => ['nullable', 'string', 'max:1000'],
            'supervisor_id' => ['nullable', 'string', 'exists:users,id'],
            'forzar_cierre' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto_cierre_efectivo.required' => 'El monto de cierre en efectivo es requerido',
            'monto_cierre_efectivo.numeric' => 'El monto de cierre debe ser un número',
            'monto_cierre_efectivo.min' => 'El monto de cierre no puede ser negativo',
            'total_cuentas.required' => 'El total de cuentas es requerido',
            'total_cuentas.numeric' => 'El total de cuentas debe ser un número',
            'supervisor_id.exists' => 'El supervisor seleccionado no existe',
        ];
    }
}
