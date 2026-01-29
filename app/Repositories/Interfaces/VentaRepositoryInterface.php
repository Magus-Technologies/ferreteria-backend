<?php

namespace App\Repositories\Interfaces;

use Illuminate\Support\Collection;

interface VentaRepositoryInterface
{
    public function obtenerPorApertura(string $aperturaId): Collection;
}
