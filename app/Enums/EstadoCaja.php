<?php

namespace App\Enums;

enum EstadoCaja: int
{
    case ACTIVA = 1;
    case INACTIVA = 0;

    public function label(): string
    {
        return match($this) {
            self::ACTIVA => 'Activa',
            self::INACTIVA => 'Inactiva',
        };
    }
}
