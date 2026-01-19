<?php

namespace App\Exceptions;

use Exception;

class SaldoInsuficienteException extends Exception
{
    public function __construct(string $message = "Saldo insuficiente en la caja")
    {
        parent::__construct($message, 422);
    }
}
