<?php

namespace App\DTOs\MovimientoInterno;

class CrearMovimientoInternoDTO
{
    public function __construct(
        public readonly int $subCajaOrigenId,
        public readonly int $subCajaDestinoId,
        public readonly float $monto,
        public readonly string $despliegueDePagoOrigenId,
        public readonly string $despliegueDePagoDestinoId,
        public readonly string $justificacion,
        public readonly ?string $comprobante = null,
        public readonly ?string $numeroOperacion = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            subCajaOrigenId: $data['sub_caja_origen_id'],
            subCajaDestinoId: $data['sub_caja_destino_id'],
            monto: $data['monto'],
            despliegueDePagoOrigenId: $data['despliegue_de_pago_origen_id'],
            despliegueDePagoDestinoId: $data['despliegue_de_pago_destino_id'],
            justificacion: $data['justificacion'],
            comprobante: $data['comprobante'] ?? null,
            numeroOperacion: $data['numero_operacion'] ?? null,
        );
    }
}
