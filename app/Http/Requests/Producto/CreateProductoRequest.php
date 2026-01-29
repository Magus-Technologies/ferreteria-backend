<?php

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Product fields
            'cod_producto' => 'nullable|string|unique:producto',
            'cod_barra' => 'nullable|string|unique:producto',
            'name' => 'required|string|unique:producto',
            'name_ticket' => 'required|string',
            'categoria_id' => 'required|exists:categoria,id',
            'marca_id' => 'required|exists:marca,id',
            'unidad_medida_id' => 'required|exists:unidadmedida,id',
            'accion_tecnica' => 'nullable|string',
            'img' => 'nullable|string',
            'ficha_tecnica' => 'nullable|string',
            'stock_min' => 'required|numeric|min:0',
            'stock_max' => 'nullable|integer|min:0',
            'unidades_contenidas' => 'required|numeric|min:0',
            'estado' => 'boolean',
            'permitido' => 'nullable|boolean',

            // Context
            'almacen_id' => 'required|exists:almacen,id',

            // ProductoAlmacen
            'producto_almacen' => 'required|array',
            'producto_almacen.ubicacion_id' => 'required|exists:ubicacion,id',

            // Unit derivatives (Prices)
            'unidades_derivadas' => 'required|array|min:1',
            'unidades_derivadas.*.unidad_derivada_id' => 'required|exists:unidadderivada,id',
            'unidades_derivadas.*.factor' => 'required|numeric|min:0',
            'unidades_derivadas.*.precio_publico' => 'required|numeric|min:0',
            'unidades_derivadas.*.comision_publico' => 'nullable|numeric',
            'unidades_derivadas.*.precio_especial' => 'nullable|numeric',
            'unidades_derivadas.*.comision_especial' => 'nullable|numeric',
            'unidades_derivadas.*.activador_especial' => 'nullable|numeric',
            'unidades_derivadas.*.precio_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.comision_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.activador_minimo' => 'nullable|numeric',
            'unidades_derivadas.*.precio_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.comision_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.activador_ultimo' => 'nullable|numeric',
            'unidades_derivadas.*.costo' => 'required|numeric|min:0',

            // Purchase (Initial stock)
            'compra' => 'nullable|array',
            'compra.lote' => 'nullable|string',
            'compra.vencimiento' => 'nullable|date',
            'compra.stock_entero' => 'nullable|numeric|min:0',
            'compra.stock_fraccion' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del producto es requerido',
            'name.unique' => 'Ya existe un producto con este nombre',
            'cod_producto.unique' => 'Ya existe un producto con este código',
            'cod_barra.unique' => 'Ya existe un producto con este código de barras',
            'categoria_id.required' => 'La categoría es requerida',
            'categoria_id.exists' => 'La categoría seleccionada no existe',
            'marca_id.required' => 'La marca es requerida',
            'marca_id.exists' => 'La marca seleccionada no existe',
            'unidad_medida_id.required' => 'La unidad de medida es requerida',
            'unidad_medida_id.exists' => 'La unidad de medida seleccionada no existe',
            'almacen_id.required' => 'El almacén es requerido',
            'almacen_id.exists' => 'El almacén seleccionado no existe',
            'producto_almacen.ubicacion_id.required' => 'La ubicación es requerida',
            'producto_almacen.ubicacion_id.exists' => 'La ubicación seleccionada no existe',
            'unidades_derivadas.required' => 'Debe especificar al menos una unidad derivada con precios',
            'unidades_derivadas.min' => 'Debe especificar al menos una unidad derivada con precios',
            'unidades_derivadas.*.unidad_derivada_id.required' => 'La unidad derivada es requerida',
            'unidades_derivadas.*.factor.required' => 'El factor de conversión es requerido',
            'unidades_derivadas.*.precio_publico.required' => 'El precio público es requerido',
            'unidades_derivadas.*.costo.required' => 'El costo es requerido',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cod_producto' => 'código de producto',
            'cod_barra' => 'código de barras',
            'name' => 'nombre',
            'name_ticket' => 'nombre para ticket',
            'categoria_id' => 'categoría',
            'marca_id' => 'marca',
            'unidad_medida_id' => 'unidad de medida',
            'stock_min' => 'stock mínimo',
            'stock_max' => 'stock máximo',
            'unidades_contenidas' => 'unidades contenidas',
            'almacen_id' => 'almacén',
            'producto_almacen.ubicacion_id' => 'ubicación',
        ];
    }
}
