<?php

namespace App\DTOs\PrestamoVendedor;

class CrearSolicitudEfectivoDTO
{
    public function __construct(
        public readonly string $aperturaId,
        public readonly int $vendedorPrestamistaId,
        public readonly float $montoSolicitado,
        public readonly ?string $motivo = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            aperturaId: $data['apertura_cierre_caja_id'],
            vendedorPrestamistaId: $data['vendedor_prestamista_id'],
            montoSolicitado: $data['monto_solicitado'],
            motivo: $data['motivo'] ?? null,
        );
    }
}
