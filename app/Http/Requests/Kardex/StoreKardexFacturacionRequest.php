<?php

namespace App\Http\Requests\Kardex;

use Illuminate\Foundation\Http\FormRequest;

class StoreKardexFacturacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => 'required|date',
            'codigo' => 'nullable|string|max:255',
            'producto' => 'nullable|string|max:255',
            'tipo' => 'required|string|in:venta,cotizacion,prestamo,guia',
            'movimiento' => 'nullable|string|max:255',
            'documento' => 'nullable|string|max:255',
            'unidad' => 'nullable|string|max:50',
            'cantidad' => 'required|numeric|min:0',
            'precio' => 'nullable|numeric|min:0',
            'stock_anterior' => 'nullable|numeric',
            'cant_ingreso' => 'nullable|numeric|min:0',
            'cant_salida' => 'nullable|numeric|min:0',
            'stock_actual' => 'nullable|numeric',
            'venta_id' => 'nullable|string|exists:venta,id',
            'producto_almacen_id' => 'nullable|integer|exists:productoalmacen,id',
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida',
            'tipo.required' => 'El tipo de movimiento es requerido',
            'tipo.in' => 'El tipo debe ser: venta, cotizacion, prestamo o guia',
            'cantidad.required' => 'La cantidad es requerida',
            'venta_id.exists' => 'La venta no existe',
            'producto_almacen_id.exists' => 'El producto almacén no existe',
        ];
    }
}
