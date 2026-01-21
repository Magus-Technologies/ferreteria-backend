<?php

namespace App\UseCases\CierreCaja;

use App\DTOs\CierreCaja\CierreCajaDTO;
use App\DTOs\CierreCaja\ResumenCajaDTO;
use App\Exceptions\CajaYaCerradaException;
use App\Exceptions\DiferenciaCajaExcedidaException;
use App\Repositories\Interfaces\AperturaCierreCajaRepositoryInterface;
use App\Services\CierreCaja\CalculadorResumenCaja;
use App\Services\CierreCaja\ValidadorSupervisorCaja;
use Illuminate\Support\Facades\DB;

class CerrarCajaUseCase
{
    public function __construct(
        private AperturaCierreCajaRepositoryInterface $aperturaRepository,
        private CalculadorResumenCaja $calculadorResumen,
        private ValidadorSupervisorCaja $validadorSupervisor
    ) {}

    public function ejecutar(CierreCajaDTO $dto): ResumenCajaDTO
    {
        return DB::transaction(function () use ($dto) {
            // 1. Obtener apertura activa
            $apertura = $this->aperturaRepository->obtenerAperturaActiva(
                $dto->cajaId,
                $dto->subCajaId
            );

            if (!$apertura) {
                throw new CajaYaCerradaException();
            }

            // 2. Actualizar monto de cierre primero
            $apertura->monto_cierre = $dto->montoCierre;

            // 3. Calcular resumen con el monto actualizado
            $resumen = $this->calculadorResumen->calcular($apertura);

            // 4. Validar diferencia
            $this->validarDiferencia($resumen, $dto);

            // 5. Cerrar caja
            $apertura->update([
                'fecha_cierre' => now(),
                'monto_cierre' => $dto->montoCierre,
                'observaciones_cierre' => $dto->observaciones,
                'cerrado_por' => $dto->usuarioId,
            ]);

            return $resumen;
        });
    }

    private function validarDiferencia(ResumenCajaDTO $resumen, CierreCajaDTO $dto): void
    {
        $diferencia = abs($resumen->diferencia);

        if ($diferencia > config('caja.limite_diferencia', 5)) {
            $this->validadorSupervisor->validar($dto->supervisorId);
        }

        if ($diferencia > config('caja.limite_maximo_diferencia', 50)) {
            throw new DiferenciaCajaExcedidaException($diferencia);
        }
    }
}
