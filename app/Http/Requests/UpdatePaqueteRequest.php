<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaqueteRequest extends FormRequest
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
        $paqueteId = $this->route('paquete'); // Obtener ID de la ruta

        return [
            'nombre' => 'sometimes|string|max:255|unique:paquete,nombre,' . $paqueteId,
            'descripcion' => 'nullable|string',
            'activo' => 'sometimes|boolean',
            'productos' => 'sometimes|array|min:1',
            'productos.*.producto_id' => 'required_with:productos|integer|exists:producto,id',
            'productos.*.unidad_derivada_id' => 'required_with:productos|integer|exists:unidadderivada,id',
            'productos.*.cantidad' => 'required_with:productos|numeric|min:0.001',
            'productos.*.precio_sugerido' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un paquete con este nombre',
            'productos.min' => 'Debes agregar al menos un producto',
            'productos.*.producto_id.exists' => 'Uno de los productos no existe',
            'productos.*.unidad_derivada_id.exists' => 'Una de las unidades derivadas no existe',
            'productos.*.cantidad.min' => 'La cantidad debe ser mayor a 0',
        ];
    }
}

