<?php

namespace App\Exceptions;

use Exception;

class SupervisorRequeridoException extends Exception
{
    public function __construct(?string $message = null, ?float $diferencia = null, ?float $limite = null)
    {
        if ($message) {
            // Si se proporciona un mensaje personalizado, usarlo
            parent::__construct($message, 400);
        } elseif ($diferencia !== null && $limite !== null) {
            // Si se proporcionan diferencia y límite, generar mensaje automático
            $msg = sprintf(
                'Las diferencias (S/ %.2f) superan el límite permitido (S/ %.2f). Se requiere autorización de supervisor.',
                abs($diferencia),
                $limite
            );
            parent::__construct($msg, 400);
        } else {
            // Mensaje por defecto
            parent::__construct('Se requiere autorización de supervisor', 400);
        }
    }
}
