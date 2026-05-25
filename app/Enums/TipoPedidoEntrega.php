<?php

namespace App\Enums;

enum TipoPedidoEntrega: string
{
    case Interno = 'interno';
    case Externo = 'externo';

    public function nombre(): string
    {
        return match ($this) {
            self::Interno => 'Interno',
            self::Externo => 'Externo',
        };
    }
}
