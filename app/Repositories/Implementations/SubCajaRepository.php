<?php

namespace App\Repositories\Implementations;

use App\Models\SubCaja;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SubCajaRepository implements SubCajaRepositoryInterface
{
    public function findById(string $id): ?SubCaja
    {
        return SubCaja::with(['cajaPrincipal.user'])->find($id);
    }

    public function findByCodigo(string $codigo): ?SubCaja
    {
        return SubCaja::where('codigo', $codigo)->first();
    }

    public function findByCajaPrincipalId(int $cajaPrincipalId): Collection
    {
        return SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->orderBy('tipo_caja', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function findCajaChica(int $cajaPrincipalId): ?SubCaja
    {
        return SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('tipo_caja', 'CC')
            ->first();
    }

    public function create(array $data): SubCaja
    {
        return SubCaja::create($data);
    }

    public function update(string $id, array $data): SubCaja
    {
        $subCaja = SubCaja::findOrFail($id);
        $subCaja->update($data);
        return $subCaja->fresh(['cajaPrincipal.user']);
    }

    public function delete(string $id): bool
    {
        $subCaja = SubCaja::findOrFail($id);
        return $subCaja->delete();
    }

    public function actualizarSaldo(string $id, float $nuevoSaldo): bool
    {
        return SubCaja::where('id', $id)->update(['saldo_actual' => $nuevoSaldo]);
    }

    public function generarSiguienteCodigo(string $codigoCajaPrincipal): string
    {
        $ultimaSubCaja = SubCaja::whereHas('cajaPrincipal', function ($query) use ($codigoCajaPrincipal) {
            $query->where('codigo', $codigoCajaPrincipal);
        })->orderBy('id', 'desc')->first();

        if (!$ultimaSubCaja) {
            return $codigoCajaPrincipal . '-001';
        }

        $ultimoNumero = (int) substr($ultimaSubCaja->codigo, -3);
        $nuevoNumero = $ultimoNumero + 1;

        return $codigoCajaPrincipal . '-' . str_pad($nuevoNumero, 3, '0', STR_PAD_LEFT);
    }

    public function existeConfiguracionDuplicada(
        int $cajaPrincipalId,
        array $desplieguePagoIds,
        array $tiposComprobante,
        ?string $excludeId = null
    ): bool {
        // Obtener información de los métodos de pago seleccionados
        $metodosInfo = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)
            ->with('metodoDePago')
            ->get()
            ->map(function ($despliegue) {
                return [
                    'despliegue_id' => $despliegue->id,
                    'cuenta_bancaria' => $despliegue->metodoDePago->cuenta_bancaria ?? null,
                    'nombre_titular' => $despliegue->metodoDePago->nombre_titular ?? null,
                ];
            });

        // Buscar sub-cajas con la misma configuración
        $query = SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('tipos_comprobante', json_encode($tiposComprobante));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $subCajasExistentes = $query->get();

        // Verificar si alguna sub-caja tiene la misma combinación de métodos + titular + cuenta
        foreach ($subCajasExistentes as $subCaja) {
            $desplieguePagoIdsExistentes = $subCaja->despliegues_pago_ids;

            // Si no tienen la misma cantidad de métodos, no es duplicado
            if (count($desplieguePagoIdsExistentes) !== count($desplieguePagoIds)) {
                continue;
            }

            // Si no tienen los mismos IDs de métodos, no es duplicado
            sort($desplieguePagoIdsExistentes);
            $desplieguePagoIdsOrdenados = $desplieguePagoIds;
            sort($desplieguePagoIdsOrdenados);

            if ($desplieguePagoIdsExistentes !== $desplieguePagoIdsOrdenados) {
                continue;
            }

            // Obtener información de los métodos existentes
            $metodosExistentesInfo = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIdsExistentes)
                ->with('metodoDePago')
                ->get()
                ->map(function ($despliegue) {
                    return [
                        'despliegue_id' => $despliegue->id,
                        'cuenta_bancaria' => $despliegue->metodoDePago->cuenta_bancaria ?? null,
                        'nombre_titular' => $despliegue->metodoDePago->nombre_titular ?? null,
                    ];
                });

            // Comparar titular y cuenta bancaria de cada método
            $esDuplicado = true;
            foreach ($metodosInfo as $metodoNuevo) {
                $metodoExistente = $metodosExistentesInfo->firstWhere('despliegue_id', $metodoNuevo['despliegue_id']);

                if (!$metodoExistente) {
                    $esDuplicado = false;
                    break;
                }

                // Si el titular o la cuenta son diferentes, no es duplicado
                if (
                    $metodoExistente['nombre_titular'] !== $metodoNuevo['nombre_titular'] ||
                    $metodoExistente['cuenta_bancaria'] !== $metodoNuevo['cuenta_bancaria']
                ) {
                    $esDuplicado = false;
                    break;
                }
            }

            if ($esDuplicado) {
                return true;
            }
        }

        return false;
    }

    public function validarExclusividadMetodosPago(
        int $cajaPrincipalId,
        array $desplieguePagoIds,
        ?string $excludeId = null
    ): void {
        // EXCEPCIÓN: El efectivo siempre puede ser compartido
        if (in_array('*', $desplieguePagoIds)) {
            // Si elige "*", validar que NINGUNA OTRA Caja Principal tenga métodos asignados (excepto efectivo)
            $otrasSubCajasConMetodos = SubCaja::where('caja_principal_id', '!=', $cajaPrincipalId)
                ->where('estado', 1)
                ->get();
            
            foreach ($otrasSubCajasConMetodos as $osc) {
                $ids = $osc->despliegues_pago_ids ?? [];
                if (empty($ids)) continue;
                
                if (in_array('*', $ids)) {
                    throw new \Exception("No puede asignar 'TODOS' los métodos porque la Caja Principal '{$osc->cajaPrincipal->nombre}' ya tiene asignados 'TODOS' los métodos.");
                }
                
                // Verificar si tiene algo que no sea efectivo
                $tieneNoEfectivo = \App\Models\DespliegueDePago::whereIn('id', $ids)
                    ->where('name', 'not like', '%efectivo%')
                    ->exists();
                
                if ($tieneNoEfectivo) {
                    throw new \Exception("No puede asignar 'TODOS' los métodos porque la Caja Principal '{$osc->cajaPrincipal->nombre}' ya tiene métodos de pago específicos asignados.");
                }
            }
            return;
        }

        $metodosSolicitados = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)
            ->with('metodoDePago')
            ->get();

        foreach ($metodosSolicitados as $ds) {
            $nombreMetodoSolicitado = strtolower($ds->name ?? '');
            if (str_contains($nombreMetodoSolicitado, 'efectivo')) continue;

            $mpId = $ds->metodo_de_pago_id;

            // 1. Validar contra OTRAS Cajas Principales
            $otrasSubCajas = SubCaja::where('caja_principal_id', '!=', $cajaPrincipalId)
                ->where('estado', 1)
                ->get();

            foreach ($otrasSubCajas as $osc) {
                if (in_array('*', $osc->despliegues_pago_ids)) {
                    throw new \Exception("El método '{$ds->name}' ya está reservado por la Caja Principal '{$osc->cajaPrincipal->nombre}' (que tiene todos los métodos).");
                }

                if (in_array($ds->id, $osc->despliegues_pago_ids ?? [])) {
                    throw new \Exception("El método de pago '{$ds->name}' ya está siendo usado por la Caja Principal '{$osc->cajaPrincipal->nombre}'.");
                }
                
                // También verificar si la otra caja usa OTRO despliegue pero del MISMO MetodoDePago
                $mismoMetodoEnOtraCaja = \App\Models\DespliegueDePago::whereIn('id', $osc->despliegues_pago_ids ?? [])
                    ->where('metodo_de_pago_id', $mpId)
                    ->exists();
                
                if ($mismoMetodoEnOtraCaja) {
                    throw new \Exception("El banco/método '{$ds->metodoDePago->name}' ya está vinculado a la Caja Principal '{$osc->cajaPrincipal->nombre}'. Un método de pago debe ser exclusivo de una sola Caja Principal.");
                }
            }

            // 2. Validar contra la MISMA Caja Principal (Exclusividad interna entre sub-cajas)
            $mismaCajaSubCajas = SubCaja::where('caja_principal_id', $cajaPrincipalId)
                ->where('id', '!=', $excludeId)
                ->get();

            foreach ($mismaCajaSubCajas as $msc) {
                if (in_array('*', $msc->despliegues_pago_ids ?? [])) {
                    throw new \Exception("El método '{$ds->name}' ya está en uso por la sub-caja '{$msc->nombre}' de esta misma Caja Principal.");
                }

                if (in_array($ds->id, $msc->despliegues_pago_ids ?? [])) {
                    throw new \Exception("El método '{$ds->name}' ya está asignado a la sub-caja '{$msc->nombre}'.");
                }
            }
        }
    }

    public function buscarSubCajaParaVenta(
        int $cajaPrincipalId,
        string $tipoComprobante,
        string $desplieguePagoId
    ): ?SubCaja {
        // Obtener todas las sub-cajas activas de la caja principal
        $subCajas = SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('estado', 1)
            ->get();

        $mejorCoincidencia = null;
        $mejorEspecificidad = -1;

        foreach ($subCajas as $subCaja) {
            // Verificar si acepta el tipo de comprobante
            if (!$subCaja->aceptaComprobante($tipoComprobante)) {
                continue;
            }

            // Verificar si acepta el método de pago
            if (!$subCaja->aceptaMetodoPago($desplieguePagoId)) {
                continue;
            }

            // Calcular especificidad
            $especificidad = $subCaja->calcularEspecificidad();

            // Si es más específica, la elegimos
            if ($especificidad > $mejorEspecificidad) {
                $mejorEspecificidad = $especificidad;
                $mejorCoincidencia = $subCaja;
            }
        }

        return $mejorCoincidencia;
    }

    public function buscarSubCajaParaDespliegue(int $cajaPrincipalId, string $desplieguePagoId): ?SubCaja
    {
        $subCajas = SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('estado', 1)
            ->get();

        $mejorCoincidencia = null;
        $mejorEspecificidad = -1;

        foreach ($subCajas as $subCaja) {
            if (!$subCaja->aceptaMetodoPago($desplieguePagoId)) {
                continue;
            }

            $especificidad = $subCaja->calcularEspecificidad();

            if ($especificidad > $mejorEspecificidad) {
                $mejorEspecificidad = $especificidad;
                $mejorCoincidencia = $subCaja;
            }
        }

        return $mejorCoincidencia;
    }
}
