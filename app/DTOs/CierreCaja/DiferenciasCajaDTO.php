<?php

namespace App\DTOs\CierreCaja;

class DiferenciasCajaDTO
{
    public function __construct(
        public float $efectivoEsperado,
        public float $efectivoContado,
        public float $diferenciaEfectivo,
        public float $totalEsperado,
        public float $totalContado,
        public float $diferenciaTotal,
        public float $sobrante,
        public float $faltante
    ) {}

    public function toArray(): array
    {
        return [
            'efectivo_esperado' => number_format($this->efectivoEsperado, 2, '.', ''),
            'efectivo_contado' => number_format($this->efectivoContado, 2, '.', ''),
            'diferencia_efectivo' => number_format($this->diferenciaEfectivo, 2, '.', ''),
            'total_esperado' => number_format($this->totalEsperado, 2, '.', ''),
            'total_contado' => number_format($this->totalContado, 2, '.', ''),
            'diferencia_total' => number_format($this->diferenciaTotal, 2, '.', ''),
            'sobrante' => number_format($this->sobrante, 2, '.', ''),
            'faltante' => number_format($this->faltante, 2, '.', ''),
        ];
    }
}
