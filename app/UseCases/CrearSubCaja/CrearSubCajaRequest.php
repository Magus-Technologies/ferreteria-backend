<?php

namespace App\UseCases\CrearSubCaja;

class CrearSubCajaRequest
{
    public function __construct(
        public int $cajaPrincipalId,
        public string $nombre,
        public array $desplieguePagoIds,
        public array $tiposComprobante,
        public ?string $proposito = null
    ) {}
}
