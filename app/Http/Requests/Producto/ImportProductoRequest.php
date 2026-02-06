<?php

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;

class ImportProductoRequest extends FormRequest
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
        // OPTIMIZACIÓN CRÍTICA: Validar solo estructura básica
        // NO validar cada elemento (data.*) porque causa TIMEOUT con muchos productos
        // La validación detallada se hace en el Service durante el procesamiento
        return [
            'data' => 'required|array|min:1|max:10000', // Máximo 10k productos
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
            'data.required' => 'Los datos de importación son requeridos',
            'data.array' => 'Los datos deben ser un arreglo',
            'data.min' => 'Debe proporcionar al menos un producto para importar',
            'data.*.name.required' => 'El nombre del producto es requerido en cada registro',
            'data.*.stock_min.numeric' => 'El stock mínimo debe ser un número',
            'data.*.stock_min.min' => 'El stock mínimo no puede ser negativo',
            'data.*.unidades_contenidas.min' => 'Las unidades contenidas deben ser al menos 1',
            'data.*.almacen_id.exists' => 'El almacén especificado no existe',
            'data.*.costo.numeric' => 'El costo debe ser un número',
            'data.*.precio_publico.numeric' => 'El precio público debe ser un número',
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
            'data' => 'datos de importación',
            'data.*.cod_producto' => 'código de producto',
            'data.*.cod_barra' => 'código de barras',
            'data.*.name' => 'nombre',
            'data.*.categoria' => 'categoría',
            'data.*.marca' => 'marca',
            'data.*.unidad_medida' => 'unidad de medida',
            'data.*.stock_min' => 'stock mínimo',
            'data.*.stock_max' => 'stock máximo',
            'data.*.unidades_contenidas' => 'unidades contenidas',
            'data.*.almacen_id' => 'almacén',
            'data.*.ubicacion' => 'ubicación',
            'data.*.costo' => 'costo',
            'data.*.precio_publico' => 'precio público',
        ];
    }
}
