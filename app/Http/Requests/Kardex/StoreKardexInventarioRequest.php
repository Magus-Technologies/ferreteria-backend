<?php

namespace App\Http\Requests\Kardex;

use Illuminate\Foundation\Http\FormRequest;

class StoreKardexInventarioRequest extends FormRequest
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
            'tipo' => 'required|string|in:compra,recepcion,ingreso,salida,anulacion',
            'movimiento' => 'nullable|string|max:255',
            'documento' => 'nullable|string|max:255',
            'unidad' => 'nullable|string|max:50',
            'cantidad' => 'required|numeric|min:0',
            'precio' => 'nullable|numeric|min:0',
            'stock_anterior' => 'nullable|numeric',
            'cant_ingreso' => 'nullable|numeric|min:0',
            'cant_salida' => 'nullable|numeric|min:0',
            'stock_actual' => 'nullable|numeric',
            'producto_almacen_id' => 'nullable|integer|exists:productoalmacen,id',
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida',
            'tipo.required' => 'El tipo de movimiento es requerido',
            'tipo.in' => 'El tipo debe ser: compra, recepcion, ingreso, salida o anulacion',
            'cantidad.required' => 'La cantidad es requerida',
            'producto_almacen_id.exists' => 'El producto almacén no existe',
        ];
    }
}
