<?php

namespace App\DTOs\FacturacionElectronica;

class ComprobanteElectronicoDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $ventaId,
        public readonly string $tipoComprobante,
        public readonly string $serie,
        public readonly int $numero,
        public readonly int $clienteId,
        public readonly float $subtotal,
        public readonly float $igv,
        public readonly float $total,
        public readonly string $estadoSunat,
        public readonly string $userId,
        public readonly ?string $xmlGenerado = null,
        public readonly ?string $xmlFirmado = null,
        public readonly ?string $cdrXml = null,
        public readonly ?string $numeroComprobanteReferencia = null,
    ) {}
}
