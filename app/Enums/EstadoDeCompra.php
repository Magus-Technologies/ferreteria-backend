<?php

namespace App\Enums;
// esto deberi estar
enum EstadoDeCompra: string
{
    case Pendiente = 'pendiente';
    case Completada = 'completada';
    case Anulada = 'anulada';
}
