<?php

namespace App\DTOs\CierreCaja;

use App\Models\AperturaCierreCaja;

class CierreCajaResultadoDTO
{
    public function __construct(
        public AperturaCierreCaja $apertura,
        public DiferenciasCajaDTO $diferencias,
        public array $resumen
    ) {}
}
