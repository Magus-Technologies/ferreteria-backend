<?php

namespace App\DTOs\Prestamo;

class CrearPrestamoDTO
{
    public function __construct(
        public readonly ?int $subCajaOrigenId, // Ahora es opcional
        public readonly int $subCajaDestinoId,
        public readonly int $cajaPrincipalOrigenId, // Nueva: ID de la caja principal origen
        public readonly float $monto,
        public readonly ?string $desplieguePagoId,
        public readonly ?string $motivo,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            subCajaOrigenId: $data['sub_caja_origen_id'] ?? null,
            subCajaDestinoId: $data['sub_caja_destino_id'],
            cajaPrincipalOrigenId: $data['caja_principal_origen_id'],
            monto: (float) $data['monto'],
            desplieguePagoId: $data['despliegue_de_pago_id'] ?? null,
            motivo: $data['motivo'] ?? null,
        );
    }
}
