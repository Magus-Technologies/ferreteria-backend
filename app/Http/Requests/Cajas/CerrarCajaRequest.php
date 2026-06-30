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
            'conteo_billetes_monedas.b200' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.b100' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.b50' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.b20' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.b10' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.m5' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.m2' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.m1' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.m050' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.m020' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.m010' => ['nullable', 'integer', 'min:0'],
            'conteo_billetes_monedas.m005' => ['nullable', 'integer', 'min:0'],
            'conceptos_adicionales' => ['nullable', 'array'],
            'conceptos_adicionales.*.concepto' => ['required', 'string', 'max:255'],
            'conceptos_adicionales.*.numero' => ['nullable', 'string', 'max:50'],
            'conceptos_adicionales.*.cantidad' => ['required', 'numeric', 'min:0'],
            'comentarios' => ['nullable', 'string', 'max:1000'],
            'supervisor_id' => ['nullable', 'string', 'exists:user,id'], // Cambiar a 'user' (tabla correcta) y 'string'
            'supervisor_password' => ['nullable', 'string', 'required_with:supervisor_id'], // Requerido si se envía supervisor_id
            'email_reporte' => ['nullable', 'email'],
            'whatsapp_reporte' => ['nullable', 'string'],
            'forzar_cierre' => ['nullable', 'boolean'],
            'monto_dejar_apertura' => ['nullable', 'numeric', 'min:0'],
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
