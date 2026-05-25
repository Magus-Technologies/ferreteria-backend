<?php

namespace App\Http\Requests\Entrega;

use Illuminate\Foundation\Http\FormRequest;

class ReasignarChoferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chofer_id'   => ['required', 'string', 'exists:user,id'],
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculo,id'],
        ];
    }
}
