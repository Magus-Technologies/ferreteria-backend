<?php

namespace App\DTOs\CierreCaja;

class CierreCajaDTO
{
    public function __construct(
        public int $cajaId,
        public ?int $subCajaId,
        public float $montoCierre,
        public string $usuarioId,
        public ?string $supervisorId = null,
        public ?string $supervisorPassword = null,
        public ?string $emailReporte = null,
        public ?string $whatsappReporte = null,
        public ?string $observaciones = null
    ) {}
}
