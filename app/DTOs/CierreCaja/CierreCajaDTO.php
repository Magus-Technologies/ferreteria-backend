<?php

namespace App\DTOs\CierreCaja;

class CierreCajaDTO
{
    public function __construct(
        public int $cajaId,
        public ?int $subCajaId,
        public float $montoCierre,
        public string $usuarioId,
        public ?float $montoCierreEfectivo = null,
        public ?float $montoCierreCuentas = null,
        public ?array $conteoBilletesMonedas = null,
        public ?string $supervisorId = null,
        public ?string $supervisorPassword = null,
        public ?string $emailReporte = null,
        public ?string $whatsappReporte = null,
        public ?string $observaciones = null,
        public ?float $montoDejarApertura = null
    ) {}
}
