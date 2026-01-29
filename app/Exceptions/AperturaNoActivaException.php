<?php

namespace App\Exceptions;

use Exception;

class AperturaNoActivaException extends Exception
{
    public function __construct(string $tipoCaja = 'caja')
    {
        parent::__construct("La {$tipoCaja} no tiene una apertura activa");
    }
}
