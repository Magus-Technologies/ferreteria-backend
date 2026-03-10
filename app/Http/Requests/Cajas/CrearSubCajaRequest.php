<?php

namespace App\Http\Requests\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class CrearSubCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caja_principal_id' => 'required|integer|exists:cajas_principales,id',
            'nombre' => 'required|string|max:255',
            'despliegues_pago_ids' => 'required|array|min:1',
            'despliegues_pago_ids.*' => 'required|string',
            'tipos_comprobante' => 'required|array|min:1',
            'tipos_comprobante.*' => 'required|string|in:01,03,nv',
            'proposito' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'caja_principal_id.required' => 'La caja principal es requerida',
            'caja_principal_id.exists' => 'La caja principal no existe',
            'nombre.required' => 'El nombre de la sub-caja es requerido',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'despliegues_pago_ids.required' => 'Debe seleccionar al menos un método de pago',
            'despliegues_pago_ids.array' => 'Los métodos de pago deben ser un array',
            'despliegues_pago_ids.min' => 'Debe seleccionar al menos un método de pago',
            'tipos_comprobante.required' => 'Debe seleccionar al menos un tipo de comprobante',
            'tipos_comprobante.array' => 'Los tipos de comprobante deben ser un array',
            'tipos_comprobante.min' => 'Debe seleccionar al menos un tipo de comprobante',
            'tipos_comprobante.*.in' => 'Tipo de comprobante inválido. Valores permitidos: 01 (Factura), 03 (Boleta), nv (Nota de Venta)',
            'proposito.max' => 'El propósito no puede exceder 500 caracteres',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $desplieguePagoIds = $this->input('despliegues_pago_ids', []);
            
            // Si es ["*"], no validar IDs individuales
            if (in_array('*', $desplieguePagoIds)) {
                // Validar que si usa "*", no tenga otros IDs
                if (count($desplieguePagoIds) > 1) {
                    $validator->errors()->add('despliegues_pago_ids', 'Si selecciona "*" (todos los métodos), no puede seleccionar otros métodos específicos.');
                }
                return;
            }

            // Validar que los IDs existan en desplieguedepago
            $existentes = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)->pluck('id')->toArray();
            $noExistentes = array_diff($desplieguePagoIds, $existentes);
            
            if (!empty($noExistentes)) {
                $validator->errors()->add('despliegues_pago_ids', 'Algunos métodos de pago no existen: ' . implode(', ', $noExistentes));
                return;
            }

            // --- NUEVA VALIDACIÓN: Exclusividad por Caja Principal ---
            $cajaPrincipalId = $this->input('caja_principal_id');
            
            // 1. Caso "*" (Todos los métodos)
            if (in_array('*', $desplieguePagoIds)) {
                $otrasSubCajasConMetodos = \App\Models\SubCaja::where('caja_principal_id', '!=', $cajaPrincipalId)
                    ->where('estado', 1)
                    ->get();
                
                foreach ($otrasSubCajasConMetodos as $osc) {
                    $idsOsc = $osc->despliegues_pago_ids ?? [];
                    if (empty($idsOsc)) continue;
                    
                    if (in_array('*', $idsOsc)) {
                        $validator->errors()->add('despliegues_pago_ids', "No puede asignar 'TODOS' los métodos porque la Caja Principal '{$osc->cajaPrincipal->nombre}' ya tiene asignados 'TODOS' los métodos.");
                        return;
                    }
                    
                    $tieneNoEfectivo = \App\Models\DespliegueDePago::whereIn('id', $idsOsc)
                        ->where('name', 'not like', '%efectivo%')
                        ->exists();
                    
                    if ($tieneNoEfectivo) {
                        $validator->errors()->add('despliegues_pago_ids', "No puede asignar 'TODOS' los métodos porque la Caja Principal '{$osc->cajaPrincipal->nombre}' ya tiene métodos de pago específicos asignados.");
                        return;
                    }
                }
            } 
            // 2. Casos específicos
            else {
                $metodosSolicitados = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)
                    ->with('metodoDePago')
                    ->get();

                foreach ($metodosSolicitados as $ds) {
                    $nombreMetodoSolicitado = strtolower($ds->name ?? '');
                    if (str_contains($nombreMetodoSolicitado, 'efectivo')) continue;

                    // Validar contra OTRAS Cajas Principales
                    $otrasSubCajas = \App\Models\SubCaja::where('caja_principal_id', '!=', $cajaPrincipalId)
                        ->where('estado', 1)
                        ->get();

                    foreach ($otrasSubCajas as $osc) {
                        if (in_array('*', $osc->despliegues_pago_ids ?? [])) {
                            $validator->errors()->add('despliegues_pago_ids', "El método '{$ds->name}' ya está reservado por la Caja Principal '{$osc->cajaPrincipal->nombre}' (que tiene todos los métodos).");
                            return;
                        }

                        // Verificar si la otra caja usa este MetodoDePago (a través de cualquier despliegue)
                        $mismoMetodoEnOtraCaja = \App\Models\DespliegueDePago::whereIn('id', $osc->despliegues_pago_ids ?? [])
                            ->where('metodo_de_pago_id', $ds->metodo_de_pago_id)
                            ->exists();
                        
                        if ($mismoMetodoEnOtraCaja) {
                            $validator->errors()->add('despliegues_pago_ids', "El banco/método '{$ds->metodoDePago->name}' ya está vinculado a la Caja Principal '{$osc->cajaPrincipal->nombre}'. Un método de pago debe ser exclusivo de una sola Caja Principal.");
                            return;
                        }
                    }

                    // Validar contra la MISMA Caja Principal (Exclusividad entre sub-cajas)
                    $mismaCajaSubCajas = \App\Models\SubCaja::where('caja_principal_id', $cajaPrincipalId)
                        ->get();

                    foreach ($mismaCajaSubCajas as $msc) {
                        if (in_array('*', $msc->despliegues_pago_ids ?? [])) {
                            $validator->errors()->add('despliegues_pago_ids', "El método '{$ds->name}' ya está en uso por la sub-caja '{$msc->nombre}' de esta misma Caja Principal.");
                            return;
                        }

                        if (in_array($ds->id, $msc->despliegues_pago_ids ?? [])) {
                            $validator->errors()->add('despliegues_pago_ids', "El método '{$ds->name}' ya está asignado a la sub-caja '{$msc->nombre}'.");
                            return;
                        }
                    }
                }
            }
        });
    }
}
