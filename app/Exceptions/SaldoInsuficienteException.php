<?php

namespace App\Exceptions;

use Exception;

class SaldoInsuficienteException extends Exception
{
    public function __construct(
        public readonly float $saldoDisponible,
        public readonly float $montoSolicitado,
    ) {
        parent::__construct('Saldo insuficiente en la caja');
    }
}
