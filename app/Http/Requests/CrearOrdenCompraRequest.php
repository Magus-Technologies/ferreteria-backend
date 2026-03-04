<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requerimiento_id' => ['nullable', 'integer', 'exists:requerimientos_internos,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedor,id'],
            'fecha' => ['required', 'date'],
            'tipo_moneda' => ['nullable', 'in:s,d'],
            'tipo_de_cambio' => ['nullable', 'numeric', 'min:0'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'tipo_documento' => ['nullable', 'string', 'max:10'],
            'serie' => ['nullable', 'string', 'max:20'],
            'numero' => ['nullable', 'string', 'max:20'],
            'guia' => ['nullable', 'string', 'max:50'],
            'percepcion' => ['nullable', 'numeric', 'min:0'],
            'forma_de_pago' => ['nullable', 'in:co,cr'],
            'numero_dias' => ['nullable', 'integer', 'min:0'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'egreso_dinero_id' => ['nullable', 'string'],
            'despliegue_de_pago_id' => ['nullable', 'string'],
            'almacen_id' => ['required', 'integer', 'exists:almacen,id'],
            // Productos
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'exists:producto,id'],
            'productos.*.codigo' => ['nullable', 'string', 'max:50'],
            'productos.*.nombre' => ['nullable', 'string', 'max:255'],
            'productos.*.marca' => ['nullable', 'string', 'max:100'],
            'productos.*.unidad' => ['nullable', 'string', 'max:50'],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'productos.*.precio' => ['required', 'numeric', 'min:0'],
            'productos.*.subtotal' => ['required', 'numeric', 'min:0'],
            'productos.*.flete' => ['nullable', 'numeric', 'min:0'],
            'productos.*.vencimiento' => ['nullable', 'date'],
            'productos.*.lote' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida',
            'almacen_id.required' => 'El almacén es requerido',
            'almacen_id.exists' => 'El almacén no existe',
            'productos.required' => 'Debe agregar al menos un producto',
            'productos.min' => 'Debe agregar al menos un producto',
            'productos.*.producto_id.required' => 'El producto es requerido',
            'productos.*.producto_id.exists' => 'El producto seleccionado no existe',
            'productos.*.cantidad.required' => 'La cantidad es requerida',
            'productos.*.cantidad.min' => 'La cantidad debe ser mayor a 0',
            'productos.*.precio.required' => 'El precio es requerido',
            'productos.*.subtotal.required' => 'El subtotal es requerido',
        ];
    }
}
