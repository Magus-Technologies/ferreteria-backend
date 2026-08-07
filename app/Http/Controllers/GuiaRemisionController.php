<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuiaRemisionRequest;
use App\Http\Requests\UpdateGuiaRemisionRequest;
use App\Http\Requests\AnularGuiaRemisionRequest;
use App\Models\GuiaRemision;
use App\QueryBuilders\GuiaRemisionQueryBuilder;
use App\Services\GuiaRemisionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GuiaRemisionController extends Controller
{
    protected $guiaRemisionService;

    public function __construct(GuiaRemisionService $guiaRemisionService)
    {
        $this->guiaRemisionService = $guiaRemisionService;
    }

    /**
     * GET /api/guias-remision/siguiente-numero/preview
     * Obtiene la serie y el siguiente número sin reservarlo.
     * Replica la lógica de auto-generación de GuiaRemisionService::crear.
     */
    public function siguienteNumero(Request $request)
    {
        $request->validate([
            'tipo_guia' => 'sometimes|string|in:ELECTRONICA_REMITENTE,ELECTRONICA_TRANSPORTISTA,FISICA',
        ]);

        $tipoGuia = $request->input('tipo_guia', 'ELECTRONICA_REMITENTE');
        $serie = match ($tipoGuia) {
            'ELECTRONICA_TRANSPORTISTA' => 'V001',
            'FISICA' => 'TF01',
            default => 'T001',
        };

        $maxNumero = GuiaRemision::where('serie', $serie)->max('numero') ?? 0;

        return response()->json([
            'data' => [
                'serie' => $serie,
                'numero' => $maxNumero + 1,
            ],
        ]);
    }

    /**
     * Display a listing of the resource (todas las guías con filtros).
     */
    public function index(Request $request)
    {
        $request->validate([
            'venta_id' => 'sometimes|string',
            'cliente_id' => 'sometimes|integer',
            'almacen_origen_id' => 'sometimes|integer',
            'almacen_destino_id' => 'sometimes|integer',
            'tipo_guia' => 'sometimes|string',
            'estado' => 'sometimes|string',
            'motivo_traslado_id' => 'sometimes|integer',
            'modalidad_transporte' => 'sometimes|string',
            'fecha_emision_desde' => 'sometimes|date',
            'fecha_emision_hasta' => 'sometimes|date',
            'fecha_traslado_desde' => 'sometimes|date',
            'fecha_traslado_hasta' => 'sometimes|date',
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:-1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $queryBuilder = (new GuiaRemisionQueryBuilder())
            ->withRelations()
            ->applyFilters($request)
            ->orderByCreatedDesc();

        $perPage = (int) $request->input('per_page', 50);

        // Si per_page es -1, retornar todos (limitado a 100)
        if ($perPage === -1) {
            return response()->json([
                'data' => $queryBuilder->limit(100),
                'total' => $queryBuilder->getQuery()->count(),
            ]);
        }

        // Paginación normal
        $guias = $queryBuilder->paginate($perPage);

        return response()->json([
            'data' => $guias->items(),
            'total' => $guias->total(),
            'current_page' => $guias->currentPage(),
            'per_page' => $guias->perPage(),
            'last_page' => $guias->lastPage(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGuiaRemisionRequest $request)
    {
        try {
            $guia = $this->guiaRemisionService->crear($request->validated());

            return response()->json([
                'data' => $guia,
                'message' => 'Guía de remisión creada exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear guía de remisión',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $guia = GuiaRemision::with([
            'venta:id,serie,numero,cliente_id,almacen_id',
            'venta.cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono',
            'venta.cliente.direcciones',
            'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono,email',
            'cliente.direcciones',
            'motivoTraslado:id,codigo,descripcion',
            'chofer:id,dni,name,licencia',
            'almacenOrigen:id,name',
            'almacenDestino:id,name',
            'user:id,name,email',
            'detalles.producto.marca',
            'detalles.producto.unidadMedida',
            'detalles.unidadDerivadaInmutable',
            'detalles.productoAlmacen',
        ])->findOrFail($id);

        return response()->json(['data' => $guia]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGuiaRemisionRequest $request, string $id)
    {
        try {
            $guia = GuiaRemision::findOrFail($id);
            $guiaActualizada = $this->guiaRemisionService->actualizar($guia, $request->validated());

            return response()->json([
                'data' => $guiaActualizada,
                'message' => 'Guía de remisión actualizada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Emitir una guía (cambiar estado de BORRADOR a EMITIDA).
     */
    public function emitir(string $id)
    {
        try {
            $guia = GuiaRemision::findOrFail($id);
            $guiaEmitida = $this->guiaRemisionService->emitir($guia);

            return response()->json([
                'data' => $guiaEmitida,
                'message' => 'Guía de remisión emitida exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Anular una guía (cambiar estado de EMITIDA a ANULADA).
     */
    public function anular(AnularGuiaRemisionRequest $request, string $id)
    {
        try {
            $guia = GuiaRemision::findOrFail($id);
            $guiaAnulada = $this->guiaRemisionService->anular(
                $guia,
                $request->validated()['motivo_anulacion']
            );

            return response()->json([
                'data' => $guiaAnulada,
                'message' => 'Guía de remisión anulada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Enviar guía de remisión a SUNAT.
     */
    public function enviarSunat(string $id)
    {
        try {
            $guia = GuiaRemision::findOrFail($id);
            $resultado = $this->guiaRemisionService->enviarASunat($guia);

            return response()->json([
                'data' => $resultado,
                'message' => $resultado['mensaje'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtener datos de la guía para generar PDF (incluye QR y datos empresa).
     */
    public function getPdfData(string $id)
    {
        $guia = GuiaRemision::with([
            'venta:id,serie,numero',
            'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono,email',
            'cliente.direcciones',
            'motivoTraslado:id,codigo,descripcion',
            'chofer:id,dni,name,licencia',
            'almacenOrigen:id,name',
            'almacenDestino:id,name',
            'user:id,name,email',
            'detalles.producto.marca',
            'detalles.producto.unidadMedida',
            'detalles.unidadDerivadaInmutable',
        ])->findOrFail($id);

        return response()->json([
            'data' => [
                'guia' => $guia,
                'empresa' => [
                    'ruc' => \App\Models\Empresa::getRucEmisor(),
                    'razon_social' => config('sunat-api.razon_social'),
                    'nombre_comercial' => config('sunat-api.nombre_comercial'),
                    'direccion' => config('sunat-api.direccion'),
                    'ubigeo' => config('sunat-api.ubigeo'),
                    'departamento' => config('sunat-api.departamento'),
                    'provincia' => config('sunat-api.provincia'),
                    'distrito' => config('sunat-api.distrito'),
                ],
            ],
        ]);
    }

    /**
     * Ver el XML firmado de una guía en el navegador.
     */
    public function verXml(string $id): Response
    {
        try {
            $guia = GuiaRemision::findOrFail($id);
            $xml = $this->guiaRemisionService->obtenerXml($guia);

            return response($xml, 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', 'inline');
        } catch (\Exception $e) {
            return response('Error al obtener XML: ' . $e->getMessage(), 404)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Descargar el CDR de una guía aceptada por SUNAT.
     */
    public function descargarCdr(string $id): Response
    {
        try {
            $guia = GuiaRemision::findOrFail($id);
            $cdr = $this->guiaRemisionService->obtenerCdr($guia);
            $nombreArchivo = 'R-' . \App\Models\Empresa::getRucEmisor() . "-09-{$guia->serie}-{$guia->numero}.zip";

            return response($cdr, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', "attachment; filename=\"{$nombreArchivo}\"")
                ->header('Content-Transfer-Encoding', 'binary');
        } catch (\Exception $e) {
            return response('Error al obtener CDR: ' . $e->getMessage(), 404)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Remove the specified resource from storage (eliminar borrador).
     */
    public function destroy(string $id)
    {
        try {
            $guia = GuiaRemision::findOrFail($id);
            $this->guiaRemisionService->eliminar($guia);

            return response()->json([
                'data' => 'ok',
                'message' => 'Guía de remisión eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
