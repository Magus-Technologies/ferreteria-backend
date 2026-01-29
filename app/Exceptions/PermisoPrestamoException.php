<?php

namespace App\Exceptions;

use Exception;

class PermisoPrestamoException extends Exception
{
    public function __construct(string $accion = 'procesar')
    {
        parent::__construct("No tienes permiso para {$accion} este préstamo");
    }
}
