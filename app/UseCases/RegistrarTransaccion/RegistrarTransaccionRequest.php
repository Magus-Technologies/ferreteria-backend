<?php

namespace App\UseCases\RegistrarTransaccion;

class RegistrarTransaccionRequest
{
    public function __construct(
        public int $subCajaId,
        public string $tipoTransaccion,
        public float $monto,
        public string $descripcion,
        public ?string $referenciaId = null,
        public ?string $referenciaTipo = null
    ) {}
}
