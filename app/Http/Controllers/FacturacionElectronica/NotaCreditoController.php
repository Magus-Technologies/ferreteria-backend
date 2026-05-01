<?php

namespace App\Http\Controllers\FacturacionElectronica;

use App\DTOs\FacturacionElectronica\NotaCreditoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FacturacionElectronica\ActualizarNotaCreditoRequest;
use App\Http\Requests\FacturacionElectronica\CrearNotaCreditoRequest;
use App\Http\Resources\FacturacionElectronica\NotaCreditoResource;
use App\Services\Interfaces\NotaCreditoServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotaCreditoController extends Controller
{
    public function __construct(
        private NotaCreditoServiceInterface $notaCreditoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only([
                'estado',
                'fecha_inicio',
                'fecha_fin',
                'motivo_id',
                'serie',
                'search',
                'almacen_id',
            ]);

            $porPagina = $request->input('per_page', 15);

            if ($request->has('paginated') && $request->boolean('paginated')) {
                $notasCredito = $this->notaCreditoService->listarPaginado($filtros, $porPagina);
                return response()->json([
                    'success' => true,
                    'data' => NotaCreditoResource::collection($notasCredito->items()),
                    'pagination' => [
                        'total' => $notasCredito->total(),
                        'per_page' => $notasCredito->perPage(),
                        'current_page' => $notasCredito->currentPage(),
                        'last_page' => $notasCredito->lastPage(),
                        'from' => $notasCredito->firstItem(),
                        'to' => $notasCredito->lastItem(),
                    ],
                ]);
            }

            $notasCredito = $this->notaCreditoService->listar($filtros);

            return response()->json([
                'success' => true,
                'data' => NotaCreditoResource::collection($notasCredito),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar notas de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(CrearNotaCreditoRequest $request): JsonResponse
    {
        try {
            $dto = new NotaCreditoDTO(
                ventaId: $request->validated('venta_id'),
                motivoId: $request->validated('motivo_id'),
                serie: $request->validated('serie'),
                almacenId: $request->validated('almacen_id'),
                numero: $request->validated('numero'),
                descripcion: $request->validated('descripcion'),
                montoTotal: $request->validated('monto_total'),
                montoIgv: $request->validated('monto_igv'),
                montoSubtotal: $request->validated('monto_subtotal'),
                fecha: $request->validated('fecha') ? new \DateTime($request->validated('fecha')) : null,
                usuarioId: auth()->id(),
                observaciones: $request->validated('observaciones'),
                items: $request->validated('items')
            );

            $notaCredito = $this->notaCreditoService->crear($dto);

            return response()->json([
                'success' => true,
                'message' => 'Nota de crédito creada exitosamente',
                'data' => new NotaCreditoResource($notaCredito),
            ], 201);

        } catch (\Exception $e) {
            $statusCode = method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 600
                ? $e->getCode()
                : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $notaCredito = $this->notaCreditoService->obtenerPorId($id);

            if (!$notaCredito) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nota de crédito no encontrada',
                ], 404);
            }

            // Cargar relaciones necesarias
            $notaCredito->load([
                'venta.cliente',
                'motivo',
                'usuario',
                'almacen',
                'comprobanteReferencia',
            ]);

            // Cargar detalles del comprobante de referencia si existe
            if ($notaCredito->comprobanteReferencia) {
                $notaCredito->comprobanteReferencia->load('detalles');
            }

            return response()->json([
                'success' => true,
                'data' => new NotaCreditoResource($notaCredito),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener nota de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(ActualizarNotaCreditoRequest $request, string $id): JsonResponse
    {
        try {
            $dto = new NotaCreditoDTO(
                ventaId: null,
                motivoId: $request->validated('motivo_id'),
                serie: null,
                almacenId: null,
                numero: null,
                descripcion: $request->validated('descripcion'),
                montoTotal: $request->validated('monto_total'),
                montoIgv: $request->validated('monto_igv'),
                montoSubtotal: $request->validated('monto_subtotal'),
                fecha: null,
                usuarioId: null,
                observaciones: $request->validated('observaciones'),
                items: $request->validated('items')
            );

            $notaCredito = $this->notaCreditoService->actualizar($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Nota de crédito actualizada exitosamente',
                'data' => new NotaCreditoResource($notaCredito),
            ]);

        } catch (\Exception $e) {
            $statusCode = method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 600
                ? $e->getCode()
                : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function cancelar(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'motivo' => 'required|string|max:500',
            ]);

            $resultado = $this->notaCreditoService->cancelar($id, $request->input('motivo'));

            return response()->json([
                'success' => $resultado,
                'message' => $resultado ? 'Nota de crédito cancelada exitosamente' : 'No se pudo cancelar la nota de crédito',
            ]);

        } catch (\Exception $e) {
            $statusCode = method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 600
                ? $e->getCode()
                : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function enviarSunat(string $id): JsonResponse
    {
        try {
            $resultado = $this->notaCreditoService->enviarASunat($id, 'manual');

            return response()->json([
                'success' => true,
                'message' => $resultado['mensaje'] ?? 'Nota de crédito enviada a SUNAT',
                'data' => [
                    'modo' => $resultado['modo'] ?? null,
                    'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                    'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            $statusCode = method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 600
                ? $e->getCode()
                : 500;

            // Sanitizar el mensaje para evitar datos binarios
            $message = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
            $message = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $message);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Error al enviar nota de crédito a SUNAT',
            ], $statusCode);
        }
    }

    public function consultarEstadoSunat(string $id): JsonResponse
    {
        try {
            $resultado = $this->notaCreditoService->consultarEstadoSunat($id);

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar estado en SUNAT',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verXml(string $id): Response
    {
        try {
            $xml = $this->notaCreditoService->obtenerXml($id);

            return response($xml, 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', 'inline');

        } catch (\Exception $e) {
            return response('Error al obtener XML: ' . $e->getMessage(), 404)
                ->header('Content-Type', 'text/plain');
        }
    }

    public function descargarCdr(string $id): Response
    {
        try {
            $cdr = $this->notaCreditoService->obtenerCdr($id);
            $notaCredito = $this->notaCreditoService->obtenerPorId($id);

            $nombreArchivo = "R-" . config('sunat-api.ruc') . "-07-{$notaCredito->serie}-{$notaCredito->numero}.xml";

            return response($cdr, 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', "attachment; filename=\"{$nombreArchivo}\"");

        } catch (\Exception $e) {
            return response('Error al obtener CDR: ' . $e->getMessage(), 404)
                ->header('Content-Type', 'text/plain');
        }
    }

    public function porVenta(string $ventaId): JsonResponse
    {
        try {
            $notasCredito = $this->notaCreditoService->obtenerPorVenta($ventaId);

            return response()->json([
                'success' => true,
                'data' => NotaCreditoResource::collection($notasCredito),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notas de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validarVenta(string $ventaId): JsonResponse
    {
        try {
            $resultado = $this->notaCreditoService->validarVentaParaNotaCredito($ventaId);

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function generarPdf(string $id): JsonResponse
    {
        try {
            $notaCredito = $this->notaCreditoService->obtenerPorId($id);

            if (!$notaCredito) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nota de crédito no encontrada',
                ], 404);
            }

            // Cargar todas las relaciones necesarias para el PDF
            $notaCredito->load([
                'venta.cliente',
                'motivo',
                'usuario.empresa',
                'almacen',
                'comprobanteReferencia',  // Comprobante que se está afectando
            ]);

            // Cargar detalles del comprobante de referencia si existe
            if ($notaCredito->comprobanteReferencia) {
                $notaCredito->comprobanteReferencia->load('detalles');
            }

            return response()->json([
                'success' => true,
                'data' => new NotaCreditoResource($notaCredito),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
