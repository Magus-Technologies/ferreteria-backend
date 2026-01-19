<?php

namespace App\Exceptions;

use Exception;

class CajaNoEncontradaException extends Exception
{
    public function __construct(string $message = "Caja no encontrada")
    {
        parent::__construct($message, 404);
    }
}
