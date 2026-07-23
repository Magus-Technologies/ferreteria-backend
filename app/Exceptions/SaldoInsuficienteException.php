<?php

namespace App\Exceptions;

use Exception;

class SaldoInsuficienteException extends Exception
{
    public function __construct(
        public readonly float $saldoDisponible = 0,
        public readonly float $montoSolicitado = 0,
        ?string $mensaje = null,
    ) {
        // Mensaje personalizado opcional (varios llamadores ya lo pasaban como
        // tercer argumento y PHP lo ignoraba en silencio).
        parent::__construct($mensaje ?? 'Saldo insuficiente en la caja');
    }
}
