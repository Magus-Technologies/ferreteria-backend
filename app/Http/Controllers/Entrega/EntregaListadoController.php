<?php

namespace App\Http\Controllers\Entrega;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\ListarEntregasRequest;
use App\Http\Resources\Entrega\EntregaListadoResource;
use App\Http\Resources\Entrega\ResumenVentaResource;
use App\Repositories\Entrega\EntregaRepository;
use App\Repositories\Entrega\EntregaResumenRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntregaListadoController extends Controller
{
    public function __construct(
        private EntregaResumenRepository $resumenRepo,
        private EntregaRepository        $entregaRepo,
    ) {}

    /**
     * GET /api/entregas/resumen-ventas
     * Tabla MAESTRA: ventas con resumen de sus entregas.
     */
    public function resumenVentas(ListarEntregasRequest $request): AnonymousResourceCollection
    {
        $filtros    = $request->validated();
        $paginado   = $this->resumenRepo->listarConResumen($filtros);

        return ResumenVentaResource::collection($paginado);
    }

    /**
     * GET /api/entregas/por-venta/{ventaId}
     * Tabla DETALLE: todas las entregas de una venta seleccionada.
     */
    public function porVenta(string $ventaId): AnonymousResourceCollection
    {
        $entregas = $this->entregaRepo->porVenta($ventaId);

        return EntregaListadoResource::collection($entregas);
    }
}
