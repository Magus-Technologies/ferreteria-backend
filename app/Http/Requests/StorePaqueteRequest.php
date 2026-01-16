<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaqueteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // TODO: Agregar permisos cuando estén implementados
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255|unique:paquete,nombre',
            'descripcion' => 'nullable|string',
            'activo' => 'sometimes|boolean',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|integer|exists:producto,id',
            'productos.*.unidad_derivada_id' => 'required|integer|exists:unidadderivada,id',
            'productos.*.cantidad' => 'required|numeric|min:0.001',
            'productos.*.precio_sugerido' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del paquete es obligatorio',
            'nombre.unique' => 'Ya existe un paquete con este nombre',
            'productos.required' => 'Debes agregar al menos un producto',
            'productos.min' => 'Debes agregar al menos un producto',
            'productos.*.producto_id.exists' => 'Uno de los productos no existe',
            'productos.*.unidad_derivada_id.exists' => 'Una de las unidades derivadas no existe',
            'productos.*.cantidad.min' => 'La cantidad debe ser mayor a 0',
        ];
    }
}

