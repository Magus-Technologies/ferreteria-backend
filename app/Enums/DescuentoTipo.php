<?php

namespace App\Enums;

enum DescuentoTipo: string
{
    case Porcentaje = '%';
    case Monto = 'm';
}
