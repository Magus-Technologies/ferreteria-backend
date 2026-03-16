<?php

namespace App\Exceptions;

use Exception;

class UsuarioEnMantenimientoException extends Exception
{
    public function __construct(string $message = 'El usuario está en mantenimiento y no puede hacer despachos')
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json([
            'error' => 'USUARIO_EN_MANTENIMIENTO',
            'message' => $this->message,
        ], 409);
    }
}
