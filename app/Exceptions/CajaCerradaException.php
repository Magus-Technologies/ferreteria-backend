<?php

namespace App\Exceptions;

use Exception;

class CajaCerradaException extends Exception
{
    public function __construct(string $message = 'Debe aperturar una caja antes de realizar esta operación')
    {
        parent::__construct($message, 403);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error' => 'CAJA_CERRADA',
        ], $this->getCode());
    }
}
