<?php

namespace App\Exceptions;

use Exception;

class CajaYaCerradaException extends Exception
{
    public function __construct(string $message = 'Esta caja ya está cerrada')
    {
        parent::__construct($message, 400);
    }
}
