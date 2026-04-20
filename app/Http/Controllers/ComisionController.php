<?php

namespace App\Http\Controllers;

use App\Models\ComisionPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComisionController extends Controller
{
    /**
     * Resumen de comisiones por vendedor en un rango de fechas.
     * Devuelve: total generado por ventas, total pagado, pendiente.
     */
    public function porVendedor(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'almacen_id' => 'sometimes|integer',
            'user_id' => 'sometimes|string',
        ]);

        $desde = $request->input('desde');
        $hasta = $request->input('hasta');
        $almacenId = $request->input('almacen_id');
        $userIdFilter = $request->input('user_id');

        $generado = DB::table('venta as v')
            ->join('productoalmacenventa as pav', 'pav.venta_id', '=', 'v.id')
            ->join('unidadderivadainmutableventa as udiv', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('user as u', 'u.id', '=', 'v.user_id')
            ->where('v.estado_de_venta', '!=', 'an')
            ->when($desde, fn($q) => $q->whereDate('v.fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('v.fecha', '<=', $hasta))
            ->when($almacenId, fn($q) => $q->where('v.almacen_id', $almacenId))
            ->when($userIdFilter, fn($q) => $q->where('v.user_id', $userIdFilter))
            ->groupBy('v.user_id', 'u.name', 'u.email')
            ->select([
                'v.user_id',
                'u.name as vendedor',
                'u.email',
                DB::raw('COUNT(DISTINCT v.id) as total_ventas'),
                DB::raw('COALESCE(SUM(udiv.comision * udiv.cantidad), 0) as comision_generada'),
            ])
            ->get()
            ->keyBy('user_id');

        $pagado = DB::table('comision_pago')
            ->when($desde, fn($q) => $q->whereDate('fecha_pago', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('fecha_pago', '<=', $hasta))
            ->when($userIdFilter, fn($q) => $q->where('user_id', $userIdFilter))
            ->groupBy('user_id')
            ->select([
                'user_id',
                DB::raw('COALESCE(SUM(monto_pagado), 0) as comision_pagada'),
            ])
            ->get()
            ->keyBy('user_id');

        $userIds = $generado->keys()->merge($pagado->keys())->unique();

        $usuarios = DB::table('user')
            ->whereIn('id', $userIds)
            ->select('id', 'name', 'email')
            ->get()
            ->keyBy('id');

        $data = $userIds->map(function ($userId) use ($generado, $pagado, $usuarios) {
            $gen = $generado->get($userId);
            $pag = $pagado->get($userId);
            $usuario = $usuarios->get($userId);

            $generada = (float) ($gen->comision_generada ?? 0);
            $pagada = (float) ($pag->comision_pagada ?? 0);

            return [
                'user_id' => $userId,
                'vendedor' => $gen->vendedor ?? ($usuario->name ?? null),
                'email' => $gen->email ?? ($usuario->email ?? null),
                'total_ventas' => (int) ($gen->total_ventas ?? 0),
                'comision_generada' => round($generada, 2),
                'comision_pagada' => round($pagada, 2),
                'comision_pendiente' => round($generada - $pagada, 2),
            ];
        })->values();

        $resumen = [
            'total_vendedores' => $data->count(),
            'total_generado' => round($data->sum('comision_generada'), 2),
            'total_pagado' => round($data->sum('comision_pagada'), 2),
            'total_pendiente' => round($data->sum('comision_pendiente'), 2),
        ];

        return response()->json([
            'data' => $data,
            'resumen' => $resumen,
        ]);
    }

    /**
     * Detalle de comisiones por venta de un vendedor.
     */
    public function detalleVendedor(Request $request, string $userId): JsonResponse
    {
        $request->validate([
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'almacen_id' => 'sometimes|integer',
        ]);

        $desde = $request->input('desde');
        $hasta = $request->input('hasta');
        $almacenId = $request->input('almacen_id');

        $detalle = DB::table('venta as v')
            ->join('productoalmacenventa as pav', 'pav.venta_id', '=', 'v.id')
            ->join('unidadderivadainmutableventa as udiv', 'udiv.producto_almacen_venta_id', '=', 'pav.id')
            ->join('unidadderivadainmutable as udi', 'udi.id', '=', 'udiv.unidad_derivada_inmutable_id')
            ->leftJoin('producto as p', 'p.id', '=', 'udi.producto_id')
            ->leftJoin('cliente as c', 'c.id', '=', 'v.cliente_id')
            ->leftJoin('almacen as a', 'a.id', '=', 'v.almacen_id')
            ->where('v.user_id', $userId)
            ->where('v.estado_de_venta', '!=', 'an')
            ->when($desde, fn($q) => $q->whereDate('v.fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('v.fecha', '<=', $hasta))
            ->when($almacenId, fn($q) => $q->where('v.almacen_id', $almacenId))
            ->orderBy('v.fecha', 'desc')
            ->select([
                'v.id as venta_id',
                'v.tipo_documento',
                'v.serie',
                'v.numero',
                'v.fecha',
                'a.name as almacen',
                'c.razon_social as cliente',
                'p.name as producto',
                'udiv.cantidad',
                'udiv.precio',
                'udiv.comision',
                DB::raw('(udiv.comision * udiv.cantidad) as comision_total'),
            ])
            ->get()
            ->map(function ($row) {
                return [
                    'venta_id' => $row->venta_id,
                    'comprobante' => trim(($row->serie ?? '') . '-' . ($row->numero ?? '')),
                    'tipo_documento' => $row->tipo_documento,
                    'fecha' => $row->fecha,
                    'almacen' => $row->almacen,
                    'cliente' => $row->cliente,
                    'producto' => $row->producto,
                    'cantidad' => (float) $row->cantidad,
                    'precio' => (float) $row->precio,
                    'comision' => (float) $row->comision,
                    'comision_total' => round((float) $row->comision_total, 2),
                ];
            });

        return response()->json([
            'data' => $detalle,
            'total_comision' => round($detalle->sum('comision_total'), 2),
        ]);
    }

    /**
     * Listar pagos de comisiones registrados (historial).
     */
    public function historialPagos(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'user_id' => 'sometimes|string',
        ]);

        $query = ComisionPago::with(['vendedor:id,name,email', 'pagadoPor:id,name'])
            ->orderBy('fecha_pago', 'desc');

        if ($desde = $request->input('desde')) {
            $query->whereDate('fecha_pago', '>=', $desde);
        }
        if ($hasta = $request->input('hasta')) {
            $query->whereDate('fecha_pago', '<=', $hasta);
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Registrar un pago de comisión a un vendedor.
     */
    public function registrarPago(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string|exists:user,id',
            'monto_pagado' => 'required|numeric|min:0.01',
            'periodo_desde' => 'required|date',
            'periodo_hasta' => 'required|date|after_or_equal:periodo_desde',
            'fecha_pago' => 'required|date',
            'metodo_pago' => 'nullable|string|max:50',
            'observacion' => 'nullable|string',
        ]);

        $validated['pagado_por'] = $request->user()->id;

        $pago = ComisionPago::create($validated);
        $pago->load(['vendedor:id,name,email', 'pagadoPor:id,name']);

        return response()->json([
            'data' => $pago,
        ], 201);
    }

    /**
     * Eliminar un pago de comisión.
     */
    public function eliminarPago(string $id): JsonResponse
    {
        $pago = ComisionPago::findOrFail($id);
        $pago->delete();

        return response()->json(['message' => 'Pago eliminado']);
    }
}
