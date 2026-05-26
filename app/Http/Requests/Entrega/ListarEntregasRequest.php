<?php

namespace App\Http\Requests\Entrega;

use Illuminate\Foundation\Http\FormRequest;

class ListarEntregasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_desde'        => ['nullable', 'date'],
            'fecha_hasta'        => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'search'             => ['nullable', 'string', 'max:100'],
            'estado'             => ['nullable', 'string'],
            'tipo_entrega'       => ['nullable', 'string', 'exists:tipo_entrega,codigo'],
            'chofer_id'          => ['nullable', 'string', 'exists:user,id'],
            'solo_con_pendientes'=> ['nullable', 'boolean'],
            'solo_sin_entregas'  => ['nullable', 'boolean'],
            'solo_programadas'   => ['nullable', 'boolean'],
            'per_page'           => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'               => ['nullable', 'integer', 'min:1'],
        ];
    }
}
