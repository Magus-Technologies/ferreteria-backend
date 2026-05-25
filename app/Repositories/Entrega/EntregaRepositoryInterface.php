<?php

namespace App\Repositories\Entrega;

use App\Models\Entrega;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EntregaRepositoryInterface
{
    public function findById(int $id): ?Entrega;

    public function findByIdOrFail(int $id): Entrega;

    /** Entregas de una venta con detalles cargados */
    public function porVenta(string $ventaId): Collection;

    /** Listado filtrado para la tabla detalle */
    public function listadoFiltrado(array $filtros): LengthAwarePaginator;
}
