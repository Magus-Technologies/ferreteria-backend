<?php

namespace App\Exceptions;

use Exception;

class MetodoPagoInvalidoException extends Exception
{
    public function __construct(string $message = 'Método de pago inválido para esta operación')
    {
        parent::__construct($message);
    }
}
