<?php

namespace App\Enums;

enum EstadoSunat: string
{
    case PENDIENTE = 'PENDIENTE';
    case PROCESANDO = 'PROCESANDO';
    case ACEPTADO = 'ACEPTADO';
    case RECHAZADO = 'RECHAZADO';
    case CANCELADO = 'CANCELADO';
    case BAJA = 'BAJA';
}
