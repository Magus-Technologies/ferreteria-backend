<?php

namespace App\DTOs\Prestamo;

class AprobarPrestamoDTO
{
    public function __construct(
        public readonly string $prestamoId,
        public readonly string $aprobadorId,
        public readonly int $subCajaOrigenId, // Nueva: Se selecciona al aprobar
    ) {}
}
