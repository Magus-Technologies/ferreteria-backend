<?php

namespace App\Enums;

enum CanalVenta: string
{
    case Presencial = 'presencial';
    case Web = 'web';

    public function nombre(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Web        => 'Web',
        };
    }
}
