<?php

namespace App\Exceptions;

use Exception;

class DiferenciaCajaExcedidaException extends Exception
{
    public function __construct(float $diferencia)
    {
        parent::__construct(
            "La diferencia de caja (S/ " . number_format($diferencia, 2) . ") excede el límite permitido",
            422
        );
    }
}
