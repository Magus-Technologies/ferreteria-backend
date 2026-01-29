<?php

namespace App\DTOs\CierreCaja;

class CierreCajaDTO
{
    public function __construct(
        public int $cajaId,
        public ?int $subCajaId,
        public float $montoCierre,
        public int $usuarioId,
        public ?int $supervisorId = null,
        public ?string $observaciones = null
    ) {}
}
