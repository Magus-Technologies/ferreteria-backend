<?php

namespace App\Repositories\Implementations;

use App\Models\SubCaja;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SubCajaRepository implements SubCajaRepositoryInterface
{
    public function findById(int $id): ?SubCaja
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

    public function update(int $id, array $data): SubCaja
    {
        $subCaja = SubCaja::findOrFail($id);
        $subCaja->update($data);
        return $subCaja->fresh(['cajaPrincipal.user']);
    }

    public function delete(int $id): bool
    {
        $subCaja = SubCaja::findOrFail($id);
        return $subCaja->delete();
    }

    public function actualizarSaldo(int $id, float $nuevoSaldo): bool
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
        ?int $excludeId = null
    ): bool {
        $query = SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('despliegues_pago_ids', json_encode($desplieguePagoIds))
            ->where('tipos_comprobante', json_encode($tiposComprobante));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
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
}
