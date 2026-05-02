<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuiaRemisionRequest extends FormRequest
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
            'venta_id' => 'nullable|string|exists:venta,id',
            'tipo_guia' => 'required|string|in:ELECTRONICA_REMITENTE,ELECTRONICA_TRANSPORTISTA,FISICA',
            'serie' => 'nullable|string|max:10',
            'numero' => 'nullable|integer',
            'fecha_emision' => 'required|date',
            // Compara solo la parte de fecha (sin hora). El frontend envía
            // `fecha_emision` con timestamp completo (`YYYY-MM-DD HH:mm:ss`)
            // y `fecha_traslado` solo como `YYYY-MM-DD` — usar `after_or_equal`
            // de Laravel hacía que el mismo día fallara porque interpretaba
            // `fecha_traslado` como medianoche < hora de emisión.
            'fecha_traslado' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $emisionRaw = $this->input('fecha_emision');
                    if (!$emisionRaw) return;
                    $emision = \Illuminate\Support\Carbon::parse($emisionRaw)->startOfDay();
                    $traslado = \Illuminate\Support\Carbon::parse($value)->startOfDay();
                    if ($traslado->lt($emision)) {
                        $fail('La fecha de traslado debe ser igual o posterior a la fecha de emisión');
                    }
                },
            ],
            'afecta_stock' => 'sometimes|boolean',
            'cliente_id' => 'nullable|integer|exists:cliente,id',
            'comprador_id' => 'nullable|integer|exists:cliente,id',
            // remitente_id: solo aplica a GRE-Transportista (tipo_guia = ELECTRONICA_TRANSPORTISTA).
            // Es el cliente que contrata el servicio de transporte.
            'remitente_id' => 'nullable|integer|exists:cliente,id',
            'motivo_traslado_id' => 'required|integer|exists:motivos_traslado,id',
            'modalidad_transporte' => 'required|string|in:PRIVADO,PUBLICO',
            'vehiculo_placa' => 'nullable|string|max:20',
            'chofer_id' => 'nullable|integer|exists:chofer,id',
            // user_chofer_id: USER (despachador interno) que cumple rol de
            // chofer cuando es transporte PRIVADO.
            'user_chofer_id' => 'nullable|string|exists:user,id',
            'punto_partida' => 'required|string',
            'punto_llegada' => 'required|string',
            'almacen_origen_id' => 'required|integer|exists:almacen,id',
            'almacen_destino_id' => 'nullable|integer|exists:almacen,id',
            'referencia' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
            'user_id' => 'required|string|exists:user,id',
            
            // Detalles
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|integer|exists:producto,id',
            'detalles.*.producto_almacen_id' => 'required|integer|exists:productoalmacen,id',
            'detalles.*.unidad_derivada_inmutable_id' => 'required|integer',
            'detalles.*.unidad_derivada_inmutable_name' => 'required|string',
            'detalles.*.factor' => 'required|numeric|min:0',
            'detalles.*.cantidad' => 'required|numeric|min:0.001',
            'detalles.*.peso_total' => 'nullable|numeric|min:0',
            'detalles.*.unidad_derivada_venta_id' => 'nullable|integer|exists:unidadderivadainmutableventa,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha_traslado.after_or_equal' => 'La fecha de traslado debe ser igual o posterior a la fecha de emisión',
            'detalles.required' => 'Debe incluir al menos un producto en la guía',
            'detalles.min' => 'Debe incluir al menos un producto en la guía',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0',
        ];
    }
}
