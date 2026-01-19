<?php

namespace App\UseCases\CrearSubCaja;

use App\Services\Interfaces\CajaServiceInterface;

class CrearSubCajaUseCase
{
    public function __construct(
        private CajaServiceInterface $cajaService
    ) {}

    public function execute(CrearSubCajaRequest $request): CrearSubCajaResponse
    {
        $subCaja = $this->cajaService->crearSubCaja(
            $request->cajaPrincipalId,
            [
                'nombre' => $request->nombre,
                'despliegues_pago_ids' => $request->desplieguePagoIds,
                'tipos_comprobante' => $request->tiposComprobante,
                'proposito' => $request->proposito,
            ]
        );

        return new CrearSubCajaResponse(
            success: true,
            subCaja: $subCaja,
            message: 'Sub-caja creada exitosamente'
        );
    }
}
