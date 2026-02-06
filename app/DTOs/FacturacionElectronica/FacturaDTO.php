<?php

namespace App\DTOs\FacturacionElectronica;

class FacturaDTO
{
    public function __construct(
        public readonly ?string $ventaId,
        public readonly ?string $serie = null,
        public readonly ?int $numero = null,
        public readonly ?string $tipoDocumento = null, // '01' = Factura, '03' = Boleta
        public readonly ?int $almacenId = null,
        public readonly ?\DateTime $fecha = null,
        public readonly ?string $usuarioId = null,
        public readonly ?string $observaciones = null,
        public readonly ?bool $enviarAutomaticamente = true
    ) {}
}
