<?php

namespace App\DTOs\Prestamo;

class RechazarPrestamoDTO
{
    public function __construct(
        public readonly string $prestamoId,
        public readonly string $rechazadorId,
        public readonly ?string $motivoRechazo,
    ) {}
}
