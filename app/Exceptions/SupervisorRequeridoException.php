<?php

namespace App\Exceptions;

use Exception;

class SupervisorRequeridoException extends Exception
{
    public function __construct(float $diferencia, float $limite)
    {
        $message = sprintf(
            'Las diferencias (S/ %.2f) superan el límite permitido (S/ %.2f). Se requiere autorización de supervisor.',
            abs($diferencia),
            $limite
        );
        parent::__construct($message, 400);
    }
}
