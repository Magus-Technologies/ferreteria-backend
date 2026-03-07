<?php

namespace App\Enums;

enum EstadoDeCompra: string
{
    case Creado = 'cr';
    case EnEspera = 'ee';
    case Procesado = 'pr';
    case Anulado = 'an';
}
