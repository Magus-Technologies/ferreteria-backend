<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearRequerimientoInternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipoSolicitud = $this->input('tipo_solicitud');
        $esOCOSOC = in_array($tipoSolicitud, ['OC', 'SOC']);

        return [
            'titulo' => 'required|string|max:255',
            'cargo' => 'required|string|max:100',
            'fecha_requerida' => 'required|date',
            'prioridad' => 'required|in:BAJA,MEDIA,ALTA,URGENTE',
            'tipo_solicitud' => 'required|in:OC,OS,SOC',
            'observaciones' => 'nullable|string',
            'duracion_cantidad' => 'nullable|integer|min:1',
            'duracion_unidad' => 'nullable|string|max:20',
            'proveedor_sugerido_id' => ['nullable', 'integer', 'exists:proveedor,id'],
            'vehiculo_id' => ['nullable', 'integer'],
            'afecta_calendario' => ['nullable', 'boolean'],
            // OC/SOC - Productos
            'productos' => [
                $esOCOSOC ? 'required' : 'nullable',
                'array',
                $esOCOSOC ? 'min:1' : 'nullable',
            ],
            'productos.*.producto_id' => ['nullable', 'integer', 'exists:producto,id'],
            'productos.*.nombre_adicional' => ['nullable', 'string', 'max:255'],
            'productos.*.cantidad' => ['required_with:productos', 'numeric', 'min:0.001'],
            'productos.*.unidad' => ['nullable', 'string', 'max:50'],
            // OS - Servicios
            'servicios' => [
                $tipoSolicitud === 'OS' ? 'required' : 'nullable',
                'array',
                $tipoSolicitud === 'OS' ? 'min:1' : 'nullable',
            ],
            'servicios.*.descripcion_servicio' => ['required_with:servicios', 'string'],
            'servicios.*.tipo_servicio' => ['nullable', 'string', 'max:100'],
            'servicios.*.lugar_ejecucion' => ['nullable', 'string', 'max:255'],
            'servicios.*.fecha_inicio_estimada' => ['nullable', 'date'],
            'servicios.*.hora_inicio' => ['nullable', 'date_format:H:i'],
            'servicios.*.hora_fin' => ['nullable', 'date_format:H:i'],
            'servicios.*.presupuesto_referencial' => ['nullable', 'numeric', 'min:0'],
            'servicios.*.detalles' => ['nullable', 'string'],
            'servicios.*.duracion_cantidad' => ['nullable', 'integer', 'min:1'],
            'servicios.*.duracion_unidad' => ['nullable', 'string', 'in:minutos,horas,dias'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es requerido',
            'cargo.required' => 'El cargo es requerido',
            'fecha_requerida.required' => 'La fecha requerida es obligatoria',
            'tipo_solicitud.required' => 'El tipo de solicitud es requerido',
            'tipo_solicitud.in' => 'El tipo de solicitud debe ser OC, OS o SOC',
            'prioridad.in' => 'La prioridad debe ser BAJA, MEDIA, ALTA o URGENTE',
            'productos.required' => 'Los productos son requeridos para solicitudes de tipo OC o SOC',
            'productos.min' => 'Debe agregar al menos un producto',
            'productos.*.producto_id.exists' => 'El producto seleccionado no existe',
            'productos.*.cantidad.min' => 'La cantidad debe ser mayor a 0',
            'servicios.required' => 'Los servicios son requeridos para solicitudes de tipo OS',
            'servicios.min' => 'Debe agregar al menos un servicio',
            'servicios.*.descripcion_servicio.required' => 'La descripción del servicio es obligatoria',
        ];
    }
}
