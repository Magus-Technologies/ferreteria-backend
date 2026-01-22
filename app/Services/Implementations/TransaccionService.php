<?php

namespace App\Services\Implementations;

use App\Exceptions\CajaNoEncontradaException;
use App\Exceptions\SaldoInsuficienteException;
use App\Models\TransaccionCaja;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use App\Repositories\Interfaces\TransaccionCajaRepositoryInterface;
use App\Services\Interfaces\TransaccionServiceInterface;
use Illuminate\Support\Facades\DB;

class TransaccionService implements TransaccionServiceInterface
{
    public function __construct(
        private SubCajaRepositoryInterface $subCajaRepository,
        private TransaccionCajaRepositoryInterface $transaccionRepository
    ) {}

    public function registrarIngreso(
        int $subCajaId,
        float $monto,
        string $descripcion,
        ?string $referenciaId = null,
        ?string $referenciaTipo = null,
        ?array $conteoBilletesMonedas = null,
        ?string $desplieguePagoId = null
    ): TransaccionCaja {
        return DB::transaction(function () use ($subCajaId, $monto, $descripcion, $referenciaId, $referenciaTipo, $conteoBilletesMonedas, $desplieguePagoId) {
            $subCaja = $this->subCajaRepository->findById($subCajaId);

            if (!$subCaja) {
                throw new CajaNoEncontradaException();
            }

            $saldoAnterior = $subCaja->saldo_actual;
            $saldoNuevo = $saldoAnterior + $monto;

            // Crear transacción
            $transaccion = $this->transaccionRepository->create([
                'sub_caja_id' => $subCajaId,
                'tipo_transaccion' => 'ingreso',
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'descripcion' => $descripcion,
                'despliegue_pago_id' => $desplieguePagoId,
                'referencia_id' => $referenciaId,
                'referencia_tipo' => $referenciaTipo,
                'user_id' => auth()->id(),
                'fecha' => now(),
                'conteo_billetes_monedas' => $conteoBilletesMonedas,
            ]);

            // Actualizar saldo
            $this->subCajaRepository->actualizarSaldo($subCajaId, $saldoNuevo);

            return $transaccion;
        });
    }

    public function registrarEgreso(
        int $subCajaId,
        float $monto,
        string $descripcion,
        ?string $referenciaId = null,
        ?string $referenciaTipo = null,
        ?array $conteoBilletesMonedas = null,
        ?string $desplieguePagoId = null
    ): TransaccionCaja {
        return DB::transaction(function () use ($subCajaId, $monto, $descripcion, $referenciaId, $referenciaTipo, $conteoBilletesMonedas, $desplieguePagoId) {
            $subCaja = $this->subCajaRepository->findById($subCajaId);

            if (!$subCaja) {
                throw new CajaNoEncontradaException();
            }

            $saldoAnterior = $subCaja->saldo_actual;
            $saldoNuevo = $saldoAnterior - $monto;

            // Validar saldo suficiente
            if ($saldoNuevo < 0) {
                throw new SaldoInsuficienteException(
                    "Saldo insuficiente. Saldo actual: S/ {$saldoAnterior}, Monto a retirar: S/ {$monto}"
                );
            }

            // Crear transacción
            $transaccion = $this->transaccionRepository->create([
                'sub_caja_id' => $subCajaId,
                'tipo_transaccion' => 'egreso',
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $saldoNuevo,
                'descripcion' => $descripcion,
                'despliegue_pago_id' => $desplieguePagoId,
                'referencia_id' => $referenciaId,
                'referencia_tipo' => $referenciaTipo,
                'user_id' => auth()->id(),
                'fecha' => now(),
                'conteo_billetes_monedas' => $conteoBilletesMonedas,
            ]);

            // Actualizar saldo
            $this->subCajaRepository->actualizarSaldo($subCajaId, $saldoNuevo);

            return $transaccion;
        });
    }

    public function obtenerTransacciones(int $subCajaId, int $perPage = 15): array
    {
        $transacciones = $this->transaccionRepository->getBySubCaja($subCajaId, $perPage);
        return $transacciones->toArray();
    }
}
