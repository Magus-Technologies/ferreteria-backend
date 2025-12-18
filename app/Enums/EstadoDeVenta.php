<?php

namespace App\Enums;

enum EstadoDeVenta: string
{
    case Creado = 'cr';
    case EnEspera = 'ee';
    case Anulado = 'an';
    case Procesado = 'pr';
}
