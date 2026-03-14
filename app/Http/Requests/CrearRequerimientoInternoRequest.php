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
            'titulo' => ['required', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:100'],
            'fecha_requerida' => ['required', 'date'],
            'prioridad' => ['required', 'in:BAJA,MEDIA,ALTA,URGENTE'],
            'tipo_solicitud' => ['required', 'in:OC,OS,SOC'],
            'observaciones' => ['nullable', 'string'],
            'proveedor_sugerido_id' => ['nullable', 'integer', 'exists:proveedor,id'],
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
            // OS - Servicio
            'servicio' => [
                $tipoSolicitud === 'OS' ? 'required' : 'nullable',
                'array',
            ],
            'servicio.descripcion_servicio' => [$tipoSolicitud === 'OS' ? 'required' : 'nullable', 'string'],
            'servicio.tipo_servicio' => ['nullable', 'string', 'max:100'],
            'servicio.lugar_ejecucion' => ['nullable', 'string', 'max:255'],
            'servicio.fecha_inicio_estimada' => ['nullable', 'date'],
            'servicio.presupuesto_referencial' => ['nullable', 'numeric', 'min:0'],
            'servicio.duracion_cantidad' => ['nullable', 'integer', 'min:1'],
            'servicio.duracion_unidad' => ['nullable', 'string', 'in:horas,dias,semanas'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es requerido',
            'area.required' => 'El área es requerida',
            'fecha_requerida.required' => 'La fecha requerida es obligatoria',
            'tipo_solicitud.required' => 'El tipo de solicitud es requerido',
            'tipo_solicitud.in' => 'El tipo de solicitud debe ser OC, OS o SOC',
            'prioridad.in' => 'La prioridad debe ser BAJA, MEDIA, ALTA o URGENTE',
            'productos.required' => 'Los productos son requeridos para solicitudes de tipo OC o SOC',
            'productos.min' => 'Debe agregar al menos un producto',
            'productos.*.producto_id.exists' => 'El producto seleccionado no existe',
            'productos.*.cantidad.min' => 'La cantidad debe ser mayor a 0',
            'servicio.required' => 'Los datos del servicio son requeridos para solicitudes de tipo OS',
            'servicio.descripcion_servicio.required' => 'La descripción del servicio es obligatoria',
        ];
    }
}
