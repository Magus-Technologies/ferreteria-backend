<?php

namespace App\Services\Interfaces;

use App\DTOs\FacturacionElectronica\NotaDebitoDTO;
use App\Models\NotaDebito;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotaDebitoServiceInterface
{
    /**
     * Crear una nueva nota de débito
     */
    public function crear(NotaDebitoDTO $dto): NotaDebito;

    /**
     * Obtener nota de débito por ID
     */
    public function obtenerPorId(string $id): ?NotaDebito;

    /**
     * Listar notas de débito con filtros
     */
    public function listar(array $filtros = []): Collection;

    /**
     * Listar notas de débito paginadas
     */
    public function listarPaginado(array $filtros = [], int $porPagina = 15): LengthAwarePaginator;

    /**
     * Obtener notas de débito por venta
     */
    public function obtenerPorVenta(string $ventaId): Collection;

    /**
     * Actualizar nota de débito
     */
    public function actualizar(string $id, NotaDebitoDTO $dto): NotaDebito;

    /**
     * Cancelar nota de débito
     */
    public function cancelar(string $id, string $motivo): bool;

    /**
     * Enviar nota de débito a SUNAT
     */
    public function enviarASunat(string $id, string $modoEnvio = 'manual'): array;

    /**
     * Consultar estado en SUNAT
     */
    public function consultarEstadoSunat(string $id): array;

    /**
     * Obtener XML de la nota de débito
     */
    public function obtenerXml(string $id): string;

    /**
     * Obtener CDR de la nota de débito
     */
    public function obtenerCdr(string $id): string;

    /**
     * Validar si una venta puede tener nota de débito
     */
    public function validarVentaParaNotaDebito(string $ventaId): array;

    /**
     * Calcular totales de la nota de débito
     */
    public function calcularTotales(array $items): array;
}
