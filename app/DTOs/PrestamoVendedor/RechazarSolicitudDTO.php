<?php

namespace App\DTOs\PrestamoVendedor;

class RechazarSolicitudDTO
{
    public function __construct(
        public readonly ?string $comentario = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            comentario: $data['comentario'] ?? null,
        );
    }
}
