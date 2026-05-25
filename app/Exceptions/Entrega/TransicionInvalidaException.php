<?php

namespace App\Exceptions\Entrega;

use Exception;

class TransicionInvalidaException extends Exception
{
    public function __construct(string $desde, string $hasta)
    {
        parent::__construct("No se puede transicionar de '{$desde}' a '{$hasta}'");
    }
}
