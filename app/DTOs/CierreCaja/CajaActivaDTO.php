<?php

namespace App\DTOs\CierreCaja;

use App\Models\AperturaCierreCaja;

class CajaActivaDTO
{
    public function __construct(
        public AperturaCierreCaja $apertura,
        public array $resumen
    ) {}
}
