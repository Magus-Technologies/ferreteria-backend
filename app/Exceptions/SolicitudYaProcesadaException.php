<?php

namespace App\Exceptions;

use Exception;

class SolicitudYaProcesadaException extends Exception
{
    public function __construct(string $message = 'La solicitud ya fue procesada')
    {
        parent::__construct($message);
    }
}
