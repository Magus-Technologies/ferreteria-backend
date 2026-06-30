<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class AperturarCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tieneVendedores = !empty($this->input('vendedores'));

        return [
            'caja_principal_id' => 'required|integer|exists:cajas_principales,id',
            'monto_apertura' => $tieneVendedores
                ? 'nullable|numeric|min:0.01'
                : 'required|numeric|min:0.01',

            // Distribución a vendedores (opcional)
            'vendedores' => 'nullable|array',
            'vendedores.*.user_id' => 'required|string|exists:user,id',
            'vendedores.*.monto' => 'required|numeric|min:0.01',
            'vendedores.*.conteo_billetes_monedas' => 'nullable|array',

            // Parte del monto que viene de efectivo asignado de otro cierre (opcional)
            'monto_asignado' => 'nullable|numeric|min:0',
            
            // Conteo de billetes y monedas (opcional)
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
            
            // Opciones de envío de ticket
            'enviar_ticket' => 'nullable|boolean',
            'email_destino' => 'nullable|email',
        ];
    }

    public function messages(): array
    {
        return [
            'caja_principal_id.required' => 'La caja principal es requerida',
            'caja_principal_id.exists' => 'La caja principal no existe',
            'monto_apertura.numeric' => 'El monto debe ser un número',
            'monto_apertura.min' => 'El monto de apertura debe ser mayor a 0',
            'vendedores.*.monto.min' => 'El monto asignado a cada vendedor debe ser mayor a 0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $vendedores = $this->input('vendedores', []);
            $montoApertura = (float) $this->input('monto_apertura', 0);

            if (!empty($vendedores)) {
                // Cada vendedor debe tener monto > 0
                foreach ($vendedores as $index => $vendedor) {
                    $monto = (float) ($vendedor['monto'] ?? 0);
                    if ($monto <= 0) {
                        $validator->errors()->add(
                            "vendedores.{$index}.monto",
                            'El monto de cada vendedor debe ser mayor a S/. 0.00'
                        );
                    }
                }

                // El total también debe ser mayor a 0
                $totalVendedores = collect($vendedores)->sum(fn($v) => (float) ($v['monto'] ?? 0));
                if ($totalVendedores <= 0) {
                    $validator->errors()->add('vendedores', 'El monto total a distribuir debe ser mayor a S/. 0.00');
                }
            } elseif ($montoApertura <= 0) {
                $validator->errors()->add('monto_apertura', 'El monto de apertura debe ser mayor a S/. 0.00');
            }
        });
    }
}
