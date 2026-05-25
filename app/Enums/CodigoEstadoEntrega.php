<?php

namespace App\Enums;

enum CodigoEstadoEntrega: string
{
    case Pendiente  = 'pe';
    case EnCamino   = 'ec';
    case Entregado  = 'en';
    case Cancelado  = 'ca';

    public function nombre(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnCamino  => 'En Camino',
            self::Entregado => 'Entregado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function esFinal(): bool
    {
        return match ($this) {
            self::Entregado, self::Cancelado => true,
            default                          => false,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'orange',
            self::EnCamino  => 'blue',
            self::Entregado => 'green',
            self::Cancelado => 'red',
        };
    }
}
