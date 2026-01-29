<?php

namespace App\Exceptions;

use Exception;

class AperturaNoEncontradaException extends Exception
{
    public function __construct(string $message = 'No tienes una caja abierta')
    {
        parent::__construct($message, 404);
    }
}
