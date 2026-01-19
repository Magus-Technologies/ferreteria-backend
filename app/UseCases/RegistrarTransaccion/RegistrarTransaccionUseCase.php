<?php

namespace App\UseCases\RegistrarTransaccion;

use App\Services\Interfaces\TransaccionServiceInterface;

class RegistrarTransaccionUseCase
{
    public function __construct(
        private TransaccionServiceInterface $transaccionService
    ) {}

    public function execute(RegistrarTransaccionRequest $request): RegistrarTransaccionResponse
    {
        if ($request->tipoTransaccion === 'ingreso') {
            $transaccion = $this->transaccionService->registrarIngreso(
                $request->subCajaId,
                $request->monto,
                $request->descripcion,
                $request->referenciaId,
                $request->referenciaTipo
            );
        } else {
            $transaccion = $this->transaccionService->registrarEgreso(
                $request->subCajaId,
                $request->monto,
                $request->descripcion,
                $request->referenciaId,
                $request->referenciaTipo
            );
        }

        return new RegistrarTransaccionResponse(
            success: true,
            transaccion: $transaccion,
            message: 'Transacción registrada exitosamente'
        );
    }
}
