<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuiaRemisionRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'tipo_guia' => 'sometimes|string|in:ELECTRONICA_REMITENTE,ELECTRONICA_TRANSPORTISTA,FISICA',
            'serie' => 'nullable|string|max:10',
            'numero' => 'nullable|integer',
            'fecha_emision' => 'sometimes|date',
            'fecha_traslado' => 'sometimes|date|after_or_equal:fecha_emision',
            'afecta_stock' => 'sometimes|boolean',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
            'motivo_traslado_id' => 'sometimes|integer|exists:motivos_traslado,id',
            'modalidad_transporte' => 'sometimes|string|in:PRIVADO,PUBLICO',
            'vehiculo_placa' => 'nullable|string|max:20',
            'chofer_id' => 'nullable|integer|exists:chofer,id',
            'punto_partida' => 'sometimes|string',
            'punto_llegada' => 'sometimes|string',
            'almacen_origen_id' => 'sometimes|integer|exists:almacen,id',
            'almacen_destino_id' => 'nullable|integer|exists:almacen,id',
            'referencia' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha_traslado.after_or_equal' => 'La fecha de traslado debe ser igual o posterior a la fecha de emisión',
        ];
    }
}
