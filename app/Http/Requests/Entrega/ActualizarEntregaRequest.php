<?php

namespace App\Http\Requests\Entrega;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chofer_id'          => ['nullable', 'string', 'exists:user,id'],
            'vehiculo_id'        => ['nullable', 'integer', 'exists:vehiculo,id'],
            'fecha_programada'   => ['nullable', 'date'],
            'hora_inicio'        => ['nullable', 'date_format:H:i'],
            'hora_fin'           => ['nullable', 'date_format:H:i'],
            'direccion_entrega'  => ['nullable', 'string', 'max:500'],
            'referencia_entrega' => ['nullable', 'string', 'max:500'],
            'latitud'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'           => ['nullable', 'numeric', 'between:-180,180'],
            'observaciones'      => ['nullable', 'string', 'max:1000'],
        ];
    }
}
