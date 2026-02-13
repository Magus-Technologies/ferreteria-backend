<?php

namespace App\DTOs\FacturacionElectronica;

class NotaDebitoDTO
{
    public function __construct(
        public readonly ?string $ventaId,
        public readonly ?int $motivoId,
        public readonly ?string $serie,
        public readonly ?int $almacenId,
        public readonly ?int $numero,
        public readonly ?string $descripcion,
        public readonly ?float $montoTotal,
        public readonly ?float $montoIgv,
        public readonly ?float $montoSubtotal,
        public readonly ?\DateTime $fecha,
        public readonly ?string $usuarioId,
        public readonly ?string $observaciones = null,
        public readonly ?array $items = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ventaId: $data['venta_id'] ?? null,
            motivoId: $data['motivo_id'] ?? null,
            serie: $data['serie'] ?? null,
            almacenId: $data['almacen_id'] ?? null,
            numero: $data['numero'] ?? null,
            descripcion: $data['descripcion'] ?? '',
            montoTotal: $data['monto_total'] ?? 0,
            montoIgv: $data['monto_igv'] ?? 0,
            montoSubtotal: $data['monto_subtotal'] ?? 0,
            fecha: isset($data['fecha']) ? new \DateTime($data['fecha']) : null,
            usuarioId: $data['usuario_id'] ?? null,
            observaciones: $data['observaciones'] ?? null,
            items: $data['items'] ?? [],
        );
    }
}

