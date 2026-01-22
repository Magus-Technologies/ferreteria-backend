<?php

namespace App\Exceptions;

use Exception;

class SupervisorInvalidoException extends Exception
{
    public function __construct(string $message = 'Credenciales inválidas o usuario sin permisos de supervisor')
    {
        parent::__construct($message, 401);
    }
}
