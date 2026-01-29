<?php

namespace App\Exceptions;

use Exception;

class SubCajaDuplicadaException extends Exception
{
    public function __construct(string $message = "Ya existe una sub-caja con esta configuración")
    {
        parent::__construct($message, 422);
    }
}
