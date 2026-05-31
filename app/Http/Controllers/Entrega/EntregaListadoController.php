<?php

namespace App\Http\Controllers\Entrega;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\ListarEntregasRequest;
use App\Http\Resources\Entrega\EntregaListadoResource;
use App\Http\Resources\Entrega\ResumenVentaResource;
use App\Repositories\Entrega\EntregaRepository;
use App\Repositories\Entrega\EntregaResumenRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EntregaListadoController extends Controller
{
    public function __construct(
        private EntregaResumenRepository $resumenRepo,
        private EntregaRepository        $entregaRepo,
    ) {}

    /**
     * GET /api/entregas
     * Lista plana de entregas con filtros — usada por mis-entregas.
     */
    public function listar(ListarEntregasRequest $request): AnonymousResourceCollection
    {
        $filtros  = $request->validated();
        $entregas = $this->entregaRepo->listarFlat($filtros);

        return EntregaListadoResource::collection($entregas);
    }

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

    /**
     * GET /api/entregas/reporte
     * Reporte de entregas con resumen de KPIs y listado exportable.
     * Filtros: fecha_desde, fecha_hasta, estado, tipo_entrega.
     */
    public function reporte(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_desde'  => ['nullable', 'date'],
            'fecha_hasta'  => ['nullable', 'date'],
            'estado'       => ['nullable', 'string'],
            'tipo_entrega' => ['nullable', 'string'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $desde      = $request->input('fecha_desde');
        $hasta      = $request->input('fecha_hasta');
        $estado     = $request->input('estado');
        $tipoEntrega = $request->input('tipo_entrega');
        $perPage    = (int) $request->input('per_page', 500);

        $query = DB::table('entrega as e')
            ->join('estado_entrega as est', 'est.id', '=', 'e.estado_entrega_id')
            ->join('tipo_entrega as te', 'te.id', '=', 'e.tipo_entrega_id')
            ->leftJoin('venta as v', 'v.id', '=', 'e.venta_id')
            ->leftJoin('cliente as c', 'c.id', '=', 'v.cliente_id')
            ->leftJoin('user as u', 'u.id', '=', 'e.chofer_id')
            ->select([
                'e.id',
                'e.venta_id',
                'e.fecha_creacion',
                'e.fecha_programada',
                'e.fecha_ejecutada',
                'e.direccion_entrega',
                'e.tipo_pedido',
                DB::raw("est.nombre as estado_nombre"),
                DB::raw("est.codigo as estado_codigo"),
                DB::raw("te.nombre as tipo_entrega_nombre"),
                DB::raw("te.codigo as tipo_entrega_codigo"),
                DB::raw("CONCAT(v.serie, '-', v.numero) as venta_numero"),
                DB::raw("COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos)) as cliente"),
                DB::raw("u.name as chofer"),
            ]);

        if ($desde) {
            $query->whereDate('e.fecha_creacion', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('e.fecha_creacion', '<=', $hasta);
        }
        if ($estado) {
            $query->where('est.codigo', $estado);
        }
        if ($tipoEntrega) {
            $query->where('te.codigo', $tipoEntrega);
        }

        $total = $query->count();

        // KPIs calculados sobre el mismo filtro
        $kpis = DB::table('entrega as e')
            ->join('estado_entrega as est', 'est.id', '=', 'e.estado_entrega_id')
            ->join('tipo_entrega as te', 'te.id', '=', 'e.tipo_entrega_id')
            ->when($desde, fn ($q) => $q->whereDate('e.fecha_creacion', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('e.fecha_creacion', '<=', $hasta))
            ->when($estado, fn ($q) => $q->where('est.codigo', $estado))
            ->when($tipoEntrega, fn ($q) => $q->where('te.codigo', $tipoEntrega))
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN est.codigo = 'pe' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN est.codigo = 'ec' THEN 1 ELSE 0 END) as en_camino,
                SUM(CASE WHEN est.codigo = 'en' THEN 1 ELSE 0 END) as entregadas,
                SUM(CASE WHEN est.codigo = 'ca' THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN te.codigo = 'rt' THEN 1 ELSE 0 END) as en_tienda,
                SUM(CASE WHEN te.codigo = 'de' THEN 1 ELSE 0 END) as domicilio,
                SUM(CASE WHEN te.codigo = 'pa' THEN 1 ELSE 0 END) as parciales
            ")
            ->first();

        $data = $query->orderByDesc('e.fecha_creacion')->limit($perPage)->get();

        return response()->json([
            'data'   => $data,
            'resumen' => $kpis,
            'total'  => $total,
        ]);
    }
}
