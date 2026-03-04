<?php

namespace App\Services\Interfaces;

use App\Models\OrdenCompra;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrdenCompraServiceInterface
{
    /**
     * Listar órdenes de compra con filtros y paginación
     */
    public function listarPaginado(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Obtener una orden de compra por ID
     */
    public function obtenerPorId(int $id): OrdenCompra;

    /**
     * Crear una orden de compra con sus productos
     */
    public function crear(array $data): OrdenCompra;

    /**
     * Anular una orden de compra
     */
    public function anular(int $id): OrdenCompra;

    /**
     * Aprobar una orden de compra
     */
    public function aprobar(int $id): OrdenCompra;
}
