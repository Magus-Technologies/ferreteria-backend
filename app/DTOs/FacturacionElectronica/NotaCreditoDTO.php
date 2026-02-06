<?php

namespace App\DTOs\FacturacionElectronica;

class NotaCreditoDTO
{
    public function __construct(
        public readonly ?string $ventaId,
        public readonly ?int $motivoId,
        public readonly ?string $serie,
        public readonly ?int $almacenId,
        public readonly ?int $numero = null,
        public readonly ?string $descripcion = null,
        public readonly ?float $montoTotal = null,
        public readonly ?float $montoIgv = null,
        public readonly ?float $montoSubtotal = null,
        public readonly ?\DateTime $fecha = null,
        public readonly ?string $usuarioId = null,
        public readonly ?string $observaciones = null,
        public readonly ?array $items = null
    ) {}
}
