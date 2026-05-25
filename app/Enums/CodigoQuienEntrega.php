<?php

namespace App\Enums;

enum CodigoQuienEntrega: string
{
    case Almacen  = 'almacen';
    case Vendedor = 'vendedor';
    case Chofer   = 'chofer';

    public function nombre(): string
    {
        return match ($this) {
            self::Almacen  => 'Almacén',
            self::Vendedor => 'Vendedor',
            self::Chofer   => 'Chofer',
        };
    }

    public function requiereChofer(): bool
    {
        return $this === self::Chofer;
    }
}
