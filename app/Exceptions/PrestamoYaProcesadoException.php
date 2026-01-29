<?php

namespace App\Exceptions;

use Exception;

class PrestamoYaProcesadoException extends Exception
{
    public function __construct()
    {
        parent::__construct('Este préstamo ya fue procesado');
    }
}
