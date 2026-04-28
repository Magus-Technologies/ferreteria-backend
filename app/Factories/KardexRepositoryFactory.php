<?php

namespace App\Factories;

use App\Repositories\Implementations\KardexInventarioRepository;
use App\Repositories\Implementations\KardexFacturacionRepository;
use App\Repositories\Interfaces\KardexRepositoryInterface;
use InvalidArgumentException;

class KardexRepositoryFactory
{
    /**
     * Create a kardex repository instance based on type
     */
    public static function create(string $type): KardexRepositoryInterface
    {
        return match ($type) {
            'inventario' => new KardexInventarioRepository(),
            'facturacion' => new KardexFacturacionRepository(),
            default => throw new InvalidArgumentException("Unknown kardex type: {$type}"),
        };
    }
}
