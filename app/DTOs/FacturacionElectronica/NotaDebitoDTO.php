<?php

namespace App\DTOs\FacturacionElectronica;

class NotaDebitoDTO
{
    public function __construct(
        public readonly string $ventaId,
        public readonly int $motivoId,
        public readonly string $descripcion,
        public readonly float $montoTotal,
        public readonly float $montoIgv,
        public readonly float $montoSubtotal,
        public readonly string $referenciaDocumento,
        public readonly string $fecha,
        public readonly int $almacenId,
        public readonly string $usuarioId,
        public readonly ?string $observaciones = null,
        public readonly ?string $comprobanteIdReferencia = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ventaId: $data['venta_id'],
            motivoId: $data['motivo_id'],
            descripcion: $data['descripcion'],
            montoTotal: $data['monto_total'],
            montoIgv: $data['monto_igv'],
            montoSubtotal: $data['monto_subtotal'],
            referenciaDocumento: $data['referencia_documento'],
            fecha: $data['fecha'],
            almacenId: $data['almacen_id'],
            usuarioId: $data['usuario_id'],
            observaciones: $data['observaciones'] ?? null,
            comprobanteIdReferencia: $data['comprobante_id_referencia'] ?? null,
        );
    }
}
