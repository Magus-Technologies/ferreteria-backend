<?php

namespace App\Enums;

enum CodigoTipoDespacho: string
{
    case Inmediato  = 'in';
    case Programado = 'pr';

    public function nombre(): string
    {
        return match ($this) {
            self::Inmediato  => 'Inmediato',
            self::Programado => 'Programado',
        };
    }
}
