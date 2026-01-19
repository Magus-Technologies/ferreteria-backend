<?php

namespace App\Enums;

enum EstadoPrestamoCaja: string
{
    case PENDIENTE = 'pendiente';
    case DEVUELTO = 'devuelto';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE => 'Pendiente',
            self::DEVUELTO => 'Devuelto',
            self::CANCELADO => 'Cancelado',
        };
    }
}
