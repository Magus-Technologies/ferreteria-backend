<?php

namespace App\Enums;

enum CodigoTipoEntrega: string
{
    case RecojoEnTienda     = 'rt';
    case DespachoADomicilio = 'de';
    case DespachoParcial    = 'pa';

    public function nombre(): string
    {
        return match ($this) {
            self::RecojoEnTienda     => 'Recojo en Tienda',
            self::DespachoADomicilio => 'Despacho a Domicilio',
            self::DespachoParcial    => 'Despacho Parcial',
        };
    }

    public function requiereDireccion(): bool
    {
        return $this === self::DespachoADomicilio;
    }
}
