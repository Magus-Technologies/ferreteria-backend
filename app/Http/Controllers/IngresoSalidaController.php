<?php

namespace App\Http\Controllers;

use App\Enums\TipoDocumento;
use App\Models\IngresoSalida;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenIngresoSalida;
use App\Models\UnidadDerivadaInmutableIngresoSalida;
use App\Models\HistorialUnidadDerivadaInmutableIngresoSalida;
use App\Models\UnidadDerivadaInmutable;
use App\Services\Cache\ProductoCacheService;
use App\Services\Producto\ComplementarioStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class IngresoSalidaController extends Controller
{
    /**
     * Display a listing of ingresos/salidas.
     *
     * GET /api/ingresos-salidas?almacen_id=X&tipo_documento=Ingreso
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            "almacen_id" => "nullable|integer|exists:almacen,id",
            "tipo_documento" => "nullable|string|in:Ingreso,Salida",
            "desde" => "nullable|date",
            "hasta" => "nullable|date",
            "search_producto" => "nullable|string",
            "search_proveedor" => "nullable|string",
            "observacion" => "nullable|string",
            "tipo" => "nullable|string",
            "listar_no_anuladas" => "nullable",
            "per_page" => "nullable|integer|min:1|max:500",
            "page" => "nullable|integer|min:1",
        ]);

        $query = IngresoSalida::with([
            "almacen:id,name",
            "proveedor:id,razon_social",
            "tipoIngreso:id,name",
            "user:id,name",
            "productosPorAlmacen" => function ($q) {
                $q->with([
                    "productoAlmacen.producto" => function ($pq) {
                        $pq->with('marca:id,name');
                    },
                    "unidadesDerivadas" => function ($uq) {
                        $uq->with([
                            "unidadDerivadaInmutable:id,name",
                        ]);
                    },
                ]);
            },
        ]);

        // Filtro por Almacén
        if ($request->has("almacen_id")) {
            $query->where("almacen_id", $request->almacen_id);
        }

        // Filtro por Tipo de Documento (Ingreso/Salida)
        if ($request->has("tipo_documento")) {
            $tipoDocEnum =
                $request->tipo_documento === "Ingreso"
                ? TipoDocumento::Ingreso
                : TipoDocumento::Salida;
            $query->where("tipo_documento", $tipoDocEnum->value);
        }

        // Filtro por Rango de Fechas
        if ($request->has("desde")) {
            $query->whereDate("fecha", ">=", $request->desde);
        }
        if ($request->has("hasta")) {
            $query->whereDate("fecha", "<=", $request->hasta);
        }

        // Filtro por Nombre de Producto (en el detalle)
        if ($request->has("search_producto")) {
            $search = $request->search_producto;
            $query->whereHas("productosPorAlmacen.productoAlmacen.producto", function ($q) use ($search) {
                $q->where("name", "LIKE", "%{$search}%")
                    ->orWhere("cod_producto", "LIKE", "%{$search}%");
            });
        }

        // Filtro por Proveedor
        if ($request->has("search_proveedor")) {
            $search = $request->search_proveedor;
            $query->whereHas("proveedor", function ($q) use ($search) {
                $q->where("razon_social", "LIKE", "%{$search}%")
                    ->orWhere("numero_documento", "LIKE", "%{$search}%");
            });
        }

        // Filtro por Observación (Descripción en el header)
        if ($request->has("observacion")) {
            $query->where("descripcion", "LIKE", "%{$request->observacion}%");
        }

        // Filtro por Tipo (Nombre del Tipo de Ingreso/Salida)
        if ($request->has("tipo") && $request->tipo !== 'TODOS') {
            $query->whereHas("tipoIngreso", function ($q) use ($request) {
                $q->where("name", $request->tipo);
            });
        }

        // Filtro por Estado (No Anuladas)
        if ($request->boolean("listar_no_anuladas")) {
            $query->where("estado", true);
        }

        $perPage = $request->get("per_page", 50);
        $page = $request->get("page", 1);

        $result = $query
            ->orderBy("fecha", "desc")
            ->orderBy("created_at", "desc")
            ->paginate($perPage, ["*"], "page", $page);

        // Transformación para compatibilidad
        $items = collect($result->items())
            ->map(function ($item) {
                $itemArray = $item->toArray();
                $itemArray["tipo_ingreso"] = $itemArray["tipo_ingreso"] ?? $itemArray["tipoIngreso"] ?? null;
                $itemArray["productos_por_almacen"] = $itemArray["productos_por_almacen"] ?? $itemArray["productosPorAlmacen"] ?? [];
                unset($itemArray["tipoIngreso"]);
                unset($itemArray["productosPorAlmacen"]);
                return $itemArray;
            })
            ->toArray();

        return response()->json([
            "data" => $items,
            "current_page" => $result->currentPage(),
            "last_page" => $result->lastPage(),
            "per_page" => $result->perPage(),
            "total" => $result->total(),
        ]);
    }

    /**
     * Store a newly created ingreso/salida.
     *
     * POST /api/ingresos-salidas
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "tipo_documento" => "required|string|in:Ingreso,Salida",
            "almacen_id" => "required|integer|exists:almacen,id",
            "producto_id" => "required|integer|exists:producto,id",
            "unidad_derivada_id" => "required|integer|exists:unidadderivada,id",
            "cantidad" => "required|numeric|min:0",
            "fecha" => "nullable|date",
            "tipo_ingreso_id" => "required|integer|exists:tipoingresosalida,id",
            "proveedor_id" => "nullable|integer|exists:proveedor,id",
            "descripcion" => "nullable|string",
            "lote" => "nullable|string",
            "vencimiento" => "nullable|date",
        ]);

        return DB::transaction(function () use ($validated) {
            // Convertir string a enum
            $tipoDocumentoString = $validated["tipo_documento"];
            $tipoDocumentoEnum =
                $tipoDocumentoString === "Ingreso"
                ? TipoDocumento::Ingreso
                : TipoDocumento::Salida;

            // Validar que el tipo_ingreso_id sea compatible con el tipo_documento
            $tipoIngresoSalida = \App\Models\TipoIngresoSalida::findOrFail($validated["tipo_ingreso_id"]);
            $tipoDocumentoLower = strtolower($tipoDocumentoString);
            
            if ($tipoIngresoSalida->tipo !== 'ambos' && $tipoIngresoSalida->tipo !== $tipoDocumentoLower) {
                return response()->json([
                    "message" => "El tipo '{$tipoIngresoSalida->name}' no es compatible con {$tipoDocumentoString}. Solo puede usarse para {$tipoIngresoSalida->tipo}.",
                ], 400);
            }

            $almacenId = $validated["almacen_id"];
            $productoId = $validated["producto_id"];
            $unidadDerivadaId = $validated["unidad_derivada_id"];
            $cantidad = $validated["cantidad"];

            // PASO 1: Obtener ProductoAlmacen con unidades derivadas
            $productoAlmacen = ProductoAlmacen::where(
                "producto_id",
                $productoId,
            )
                ->where("almacen_id", $almacenId)
                ->with([
                    "unidadesDerivadas" => function ($q) use (
                        $unidadDerivadaId,
                    ) {
                        $q->where(
                            "unidad_derivada_id",
                            $unidadDerivadaId,
                        )->with("unidadDerivada:id,name");
                    },
                ])
                ->firstOrFail();

            $unidadDerivada = $productoAlmacen->unidadesDerivadas->first();
            if (!$unidadDerivada) {
                return response()->json(
                    [
                        "message" =>
                        "Unidad derivada no encontrada para este producto",
                    ],
                    404,
                );
            }

            // PASO 2: Calcular cantidad en fracciones
            $esIngreso = $tipoDocumentoString === "Ingreso";
            $factor = (float) $unidadDerivada->factor;
            $cantidadFraccion = $factor * $cantidad * ($esIngreso ? 1 : -1);

            // PASO 3: Validar stock si es salida
            if (
                !$esIngreso &&
                (float) $productoAlmacen->stock_fraccion + $cantidadFraccion < 0
            ) {
                return response()->json(
                    [
                        "message" =>
                        "Stock insuficiente para realizar la salida",
                    ],
                    400,
                );
            }

            // PASO 4: Obtener Ultimo número de documento
            $ultimoIngreso = IngresoSalida::where(
                "tipo_documento",
                $tipoDocumentoEnum->value,
            )
                ->orderBy("numero", "desc")
                ->first();
            $numero = $ultimoIngreso ? $ultimoIngreso->numero + 1 : 1;

            // PASO 5: Obtener serie (hardcoded por ahora, debería venir de empresa)
            $serie = $esIngreso ? 1 : 2;

            // PASO 6: Obtener user_id del usuario autenticado
            $userId = Auth::id();

            // PASO 7: Crear IngresoSalida
            $ingresoSalida = IngresoSalida::create([
                "tipo_documento" => $tipoDocumentoEnum,
                "serie" => $serie,
                "numero" => $numero,
                "fecha" => $validated["fecha"] ?? now(),
                "almacen_id" => $almacenId,
                "tipo_ingreso_id" => $validated["tipo_ingreso_id"],
                "proveedor_id" => $validated["proveedor_id"] ?? null,
                "descripcion" => $validated["descripcion"] ?? null,
                "user_id" => $userId,
                "estado" => true,
            ]);

            // PASO 8: Crear ProductoAlmacenIngresoSalida
            $productoAlmacenIngresoSalida = ProductoAlmacenIngresoSalida::create(
                [
                    "ingreso_id" => $ingresoSalida->id,
                    "producto_almacen_id" => $productoAlmacen->id,
                    "costo" => $productoAlmacen->costo,
                ],
            );

            // PASO 9: Crear UnidadDerivadaInmutable si no existe
            $unidadDerivadaInmutable = UnidadDerivadaInmutable::firstOrCreate(
                ["name" => $unidadDerivada->unidadDerivada->name],
                ["estado" => true],
            );

            // PASO 10: Crear UnidadDerivadaInmutableIngresoSalida
            $unidadDerivadaInmutableIngresoSalida = UnidadDerivadaInmutableIngresoSalida::create(
                [
                    "producto_almacen_ingreso_salida_id" =>
                    $productoAlmacenIngresoSalida->id,
                    "unidad_derivada_inmutable_id" =>
                    $unidadDerivadaInmutable->id,
                    "factor" => $factor,
                    "cantidad" => $cantidad,
                    "cantidad_restante" => $cantidad,
                    "lote" => $validated["lote"] ?? null,
                    "vencimiento" => $validated["vencimiento"] ?? null,
                ],
            );

            // PASO 11: Crear Historial
            $stockAnterior = (float) $productoAlmacen->stock_fraccion;
            $stockNuevo = $stockAnterior + $cantidadFraccion;

            HistorialUnidadDerivadaInmutableIngresoSalida::create([
                "unidad_derivada_inmutable_ingreso_salida_id" =>
                $unidadDerivadaInmutableIngresoSalida->id,
                "stock_anterior" => $stockAnterior,
                "stock_nuevo" => $stockNuevo,
            ]);

            // PASO 12: Actualizar ProductoAlmacen (stock y costo)
            $nuevoCosto = $productoAlmacen->costo;
            if ($stockAnterior <= 0 && $esIngreso) {
                // Si el stock era 0 o negativo y es un ingreso, usar el costo actual
                $nuevoCosto = $productoAlmacen->costo;
            }

            $productoAlmacen->update([
                "stock_fraccion" => $stockNuevo,
                "costo" => $nuevoCosto,
            ]);

            // Descontar/incrementar producto complementario si existe
            ComplementarioStockService::procesarComplementario(
                $productoAlmacen->id,
                $unidadDerivada->unidad_derivada_id,
                $cantidad,
                $almacenId,
                $esIngreso
            );

            // Invalidar cache de productos del almacén
            app(ProductoCacheService::class)->invalidateProductosAlmacen($productoAlmacen->almacen_id);

            // PASO 13: Retornar resultado con relaciones
            $result = IngresoSalida::with([
                "almacen:id,name",
                "proveedor:id,razon_social",
                "tipoIngreso:id,name",
                "user:id,name",
                "productosPorAlmacen" => function ($q) {
                    $q->with([
                        "productoAlmacen.producto:id,name,cod_producto",
                        "unidadesDerivadas" => function ($uq) {
                            $uq->with([
                                "unidadDerivadaInmutable:id,name",
                                "historial",
                            ]);
                        },
                    ]);
                },
            ])->findOrFail($ingresoSalida->id);

            // Convertir resultado a array y renombrar keys manualmente
            $resultArray = $result->toArray();

            // Renombrar relaciones de nivel superior
            if (isset($resultArray["tipoIngreso"])) {
                $resultArray["tipo_ingreso"] = $resultArray["tipoIngreso"];
                unset($resultArray["tipoIngreso"]);
            }

            if (isset($resultArray["productosPorAlmacen"])) {
                $resultArray["productos_por_almacen"] =
                    $resultArray["productosPorAlmacen"];
                unset($resultArray["productosPorAlmacen"]);
            }

            return response()->json(["data" => $resultArray], 201);
        }, 5);
    }

    /**
     * Anular (soft delete) un ingreso/salida.
     *
     * DELETE /api/ingresos-salidas/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $ingresoSalida = IngresoSalida::with([
                "productosPorAlmacen.unidadesDerivadas",
            ])->findOrFail($id);

            // Verificar si ya está anulado
            if (!$ingresoSalida->estado) {
                return response()->json(
                    ["message" => "El documento ya está anulado"],
                    400
                );
            }

            $esIngreso = $ingresoSalida->tipo_documento === TipoDocumento::Ingreso;

            foreach ($ingresoSalida->productosPorAlmacen as $detalle) {
                $productoAlmacen = $detalle->productoAlmacen;
                if (!$productoAlmacen) continue;

                foreach ($detalle->unidadesDerivadas as $ud) {
                    $factor = (float) $ud->factor;
                    $cantidad = (float) $ud->cantidad;

                    // Calcular cantidad en fracciones original
                    $cantidadFraccionOriginal = $factor * $cantidad;

                    // Si era un ingreso (sumó), al anular restamos.
                    // Si era una salida (restó), al anular sumamos.
                    $reversionFraccion = $esIngreso ? -$cantidadFraccionOriginal : $cantidadFraccionOriginal;

                    $stockAnterior = (float) $productoAlmacen->stock_fraccion;
                    $stockNuevo = $stockAnterior + $reversionFraccion;

                    // Actualizar stock del producto
                    $productoAlmacen->update([
                        "stock_fraccion" => $stockNuevo,
                    ]);

                    // Registrar en historial de la unidad inmutable
                    HistorialUnidadDerivadaInmutableIngresoSalida::create([
                        "unidad_derivada_inmutable_ingreso_salida_id" => $ud->id,
                        "stock_anterior" => $stockAnterior,
                        "stock_nuevo" => $stockNuevo,
                    ]);

                    // Revertir producto complementario (inverso al original)
                    ComplementarioStockService::procesarComplementarioPorFactor(
                        $productoAlmacen->id,
                        $factor,
                        $cantidad,
                        $productoAlmacen->almacen_id,
                        !$esIngreso // Invertir: si era ingreso (se sumó compl.), ahora restar
                    );
                }

                // Invalida cache de productos del almacén
                app(ProductoCacheService::class)->invalidateProductosAlmacen($productoAlmacen->almacen_id);
            }

            // Anular el documento (cambiar estado a false)
            $ingresoSalida->update(["estado" => false]);

            return response()->json([
                "message" => "Documento anulado y stock revertido exitosamente",
                "data" => $ingresoSalida
            ]);
        });
    }
}
