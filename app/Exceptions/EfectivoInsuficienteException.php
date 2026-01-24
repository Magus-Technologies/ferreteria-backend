<?php

namespace App\Exceptions;

use Exception;

class EfectivoInsuficienteException extends Exception
{
    public function __construct(
        public readonly float $efectivoDisponible,
        public readonly float $montoSolicitado,
        string $message = 'Efectivo insuficiente'
    ) {
        parent::__construct($message);
    }
}
