<?php

namespace App\Http\Requests\Entrega;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'venta_id'             => ['required', 'string', 'exists:venta,id'],
            'tipo_entrega'         => ['required', 'string', 'exists:tipo_entrega,codigo'],
            'tipo_despacho'        => ['nullable', 'string', 'exists:tipo_despacho,codigo'],
            'quien_entrega'        => ['required', 'string', 'exists:quien_entrega,codigo'],
            'almacen_salida_id'    => ['required', 'integer', 'exists:almacen,id'],
            'chofer_id'            => [
                'nullable', 'string', 'exists:user,id',
                Rule::requiredIf(fn () => $this->input('quien_entrega') === 'chofer'),
            ],
            'vehiculo_id'          => ['nullable', 'integer', 'exists:vehiculo,id'],
            'tipo_pedido'          => ['sometimes', 'string', 'in:interno,externo'],
            'cargo_destino'        => [
                'nullable', 'string', 'max:100',
                'required_if:tipo_pedido,externo',
            ],
            'fecha_programada'     => ['nullable', 'date'],
            'hora_inicio'          => ['nullable', 'date_format:H:i'],
            'hora_fin'             => ['nullable', 'date_format:H:i'],
            'direccion_entrega'    => ['nullable', 'string', 'max:500'],
            'referencia_entrega'   => ['nullable', 'string', 'max:500'],
            'latitud'              => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'             => ['nullable', 'numeric', 'between:-180,180'],
            'observaciones'        => ['nullable', 'string', 'max:1000'],
            'productos'            => ['required', 'array', 'min:1'],
            'productos.*.unidad_derivada_venta_id' => [
                'required', 'integer', 'exists:unidadderivadainmutableventa,id',
            ],
            'productos.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'productos.*.ubicacion'=> ['nullable', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'productos.required'              => 'Debe especificar al menos un producto.',
            'chofer_id.required'              => 'Debe asignar un chofer cuando quien entrega es "chofer".',
            'cargo_destino.required_if'       => 'El cargo destino es requerido para pedidos externos.',
            'productos.*.cantidad.min'        => 'La cantidad debe ser mayor a cero.',
        ];
    }
}
