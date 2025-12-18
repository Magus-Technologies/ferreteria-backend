<?php

namespace App\Enums;

enum EstadoCotizacion: string
{
    case Pendiente = 'pe';
    case Convertida = 'co';
    case Vencida = 've';
    case Cancelada = 'ca';
}
