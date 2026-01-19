<?php

namespace App\UseCases\CrearCajaPrincipal;

use App\Services\Interfaces\CajaServiceInterface;

class CrearCajaPrincipalUseCase
{
    public function __construct(
        private CajaServiceInterface $cajaService
    ) {}

    public function execute(CrearCajaPrincipalRequest $request): CrearCajaPrincipalResponse
    {
        $cajaPrincipal = $this->cajaService->crearCajaPrincipal(
            $request->userId,
            $request->nombre
        );

        return new CrearCajaPrincipalResponse(
            success: true,
            cajaPrincipal: $cajaPrincipal,
            message: 'Caja principal creada y aperturada exitosamente. Ya puedes hacer ventas.'
        );
    }
}
