<?php

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductoRequest extends FormRequest
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
            'almacen_id' => 'required|integer|exists:almacen,id',
            'search' => 'nullable|string',
            'estado' => 'nullable|boolean',
            'categoria_id' => 'nullable|integer|exists:categoria,id',
            'marca_id' => 'nullable|integer|exists:marca,id',
            'unidad_medida_id' => 'nullable|integer|exists:unidadmedida,id',
            'accion_tecnica' => 'nullable|string',
            'ubicacion_id' => 'nullable|integer|exists:ubicacion,id',
            'cs_stock' => 'nullable|in:con_stock,sin_stock,all',
            'cs_comision' => 'nullable|in:con_comision,sin_comision,all',
            'per_page' => 'nullable|integer|min:1|max:10000', // Aumentado para soportar carga completa de productos
            'page' => 'nullable|integer|min:1',
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
            'almacen_id.required' => 'El almacén es requerido para listar productos',
            'almacen_id.exists' => 'El almacén seleccionado no existe',
            'categoria_id.exists' => 'La categoría seleccionada no existe',
            'marca_id.exists' => 'La marca seleccionada no existe',
            'unidad_medida_id.exists' => 'La unidad de medida seleccionada no existe',
            'ubicacion_id.exists' => 'La ubicación seleccionada no existe',
            'cs_stock.in' => 'El filtro de stock debe ser: con_stock, sin_stock o all',
            'cs_comision.in' => 'El filtro de comisión debe ser: con_comision, sin_comision o all',
            'per_page.min' => 'El número de elementos por página debe ser al menos 1',
            'per_page.max' => 'El número máximo de elementos por página es 10000',
        ];
    }
}
