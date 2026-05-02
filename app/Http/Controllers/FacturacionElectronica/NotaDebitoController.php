<?php

namespace App\Http\Controllers\FacturacionElectronica;

use App\DTOs\FacturacionElectronica\NotaDebitoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FacturacionElectronica\ActualizarNotaDebitoRequest;
use App\Http\Requests\FacturacionElectronica\CrearNotaDebitoRequest;
use App\Http\Resources\FacturacionElectronica\NotaDebitoResource;
use App\Services\Interfaces\NotaDebitoServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotaDebitoController extends Controller
{
    public function __construct(
        private NotaDebitoServiceInterface $notaDebitoService
    ) {}

    /**
     * Listar notas de débito
     */
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
                $notasDebito = $this->notaDebitoService->listarPaginado($filtros, $porPagina);
                return response()->json([
                    'success' => true,
                    'data' => NotaDebitoResource::collection($notasDebito->items()),
                    'pagination' => [
                        'total' => $notasDebito->total(),
                        'per_page' => $notasDebito->perPage(),
                        'current_page' => $notasDebito->currentPage(),
                        'last_page' => $notasDebito->lastPage(),
                        'from' => $notasDebito->firstItem(),
                        'to' => $notasDebito->lastItem(),
                    ],
                ]);
            }

            $notasDebito = $this->notaDebitoService->listar($filtros);

            // Log temporal para debugging
            if ($notasDebito->isNotEmpty()) {
                \Log::info('Notas de débito encontradas', [
                    'cantidad' => $notasDebito->count(),
                    'primer_id' => $notasDebito->first()->id ?? 'sin id',
                    'tipo_primer_id' => gettype($notasDebito->first()->id ?? null),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => NotaDebitoResource::collection($notasDebito),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar notas de débito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear nota de débito
     */
    public function store(CrearNotaDebitoRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $dto = new NotaDebitoDTO(
                ventaId: $validated['venta_id'],
                motivoId: $validated['motivo_id'],
                serie: $validated['serie'],
                almacenId: $validated['almacen_id'],
                numero: $validated['numero'] ?? null,
                descripcion: $validated['descripcion'] ?? '',
                montoTotal: $validated['monto_total'],
                montoIgv: $validated['monto_igv'],
                montoSubtotal: $validated['monto_subtotal'],
                fecha: isset($validated['fecha']) ? new \DateTime($validated['fecha']) : null,
                usuarioId: auth()->id(),
                observaciones: $validated['observaciones'] ?? null,
                items: $validated['items'] ?? []
            );

            $notaDebito = $this->notaDebitoService->crear($dto);

            return response()->json([
                'success' => true,
                'message' => 'Nota de débito creada exitosamente',
                'data' => new NotaDebitoResource($notaDebito),
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

    /**
     * Obtener nota de débito por ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $notaDebito = $this->notaDebitoService->obtenerPorId($id);

            if (!$notaDebito) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nota de débito no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new NotaDebitoResource($notaDebito),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener nota de débito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar nota de débito
     */
    public function update(ActualizarNotaDebitoRequest $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $dto = new NotaDebitoDTO(
                ventaId: null, // No se actualiza la venta
                motivoId: $validated['motivo_id'],
                serie: null, // No se actualiza la serie
                almacenId: null, // No se actualiza el almacén
                numero: null,
                descripcion: $validated['descripcion'] ?? '',
                montoTotal: $validated['monto_total'],
                montoIgv: $validated['monto_igv'],
                montoSubtotal: $validated['monto_subtotal'],
                fecha: null,
                usuarioId: null,
                observaciones: $validated['observaciones'] ?? null,
                items: $validated['items'] ?? []
            );

            $notaDebito = $this->notaDebitoService->actualizar($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Nota de débito actualizada exitosamente',
                'data' => new NotaDebitoResource($notaDebito),
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

    /**
     * Cancelar nota de débito
     */
    public function cancelar(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'motivo' => 'required|string|max:500',
            ]);

            $resultado = $this->notaDebitoService->cancelar($id, $request->input('motivo'));

            return response()->json([
                'success' => $resultado,
                'message' => $resultado ? 'Nota de débito cancelada exitosamente' : 'No se pudo cancelar la nota de débito',
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

    /**
     * Enviar nota de débito a SUNAT
     */
    public function enviarSunat(string $id): JsonResponse
    {
        try {
            // Log para debugging
            \Log::info('Enviando nota de débito a SUNAT', [
                'id_recibido' => $id,
                'tipo_id' => gettype($id),
            ]);

            $resultado = $this->notaDebitoService->enviarASunat($id, 'manual');

            return response()->json([
                'success' => true,
                'message' => $resultado['mensaje'] ?? 'Nota de débito enviada a SUNAT',
                'data' => [
                    'modo' => $resultado['modo'] ?? null,
                    'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                    'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al enviar nota de débito a SUNAT', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statusCode = method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 600
                ? $e->getCode()
                : 500;

            // Asegurar que el mensaje esté en UTF-8 válido
            $message = $e->getMessage();
            if (!mb_check_encoding($message, 'UTF-8')) {
                $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $statusCode);
        }
    }

    /**
     * Consultar estado en SUNAT
     */
    public function consultarEstadoSunat(string $id): JsonResponse
    {
        try {
            $resultado = $this->notaDebitoService->consultarEstadoSunat($id);

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

    /**
     * Ver XML en navegador
     */
    public function verXml(string $id): Response
    {
        try {
            $xml = $this->notaDebitoService->obtenerXml($id);

            return response($xml, 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', 'inline');

        } catch (\Exception $e) {
            return response('Error al obtener XML: ' . $e->getMessage(), 404)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Descargar CDR
     */
    public function descargarCdr(string $id): Response
    {
        try {
            $cdr = $this->notaDebitoService->obtenerCdr($id);
            $notaDebito = $this->notaDebitoService->obtenerPorId($id);

            $nombreArchivo = "R-" . \App\Models\Empresa::getRucEmisor() . "-08-{$notaDebito->serie}-{$notaDebito->numero}.xml";

            return response($cdr, 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', "attachment; filename=\"{$nombreArchivo}\"");

        } catch (\Exception $e) {
            return response('Error al obtener CDR: ' . $e->getMessage(), 404)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Obtener notas de débito por venta
     */
    public function porVenta(string $ventaId): JsonResponse
    {
        try {
            $notasDebito = $this->notaDebitoService->obtenerPorVenta($ventaId);

            return response()->json([
                'success' => true,
                'data' => NotaDebitoResource::collection($notasDebito),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notas de débito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validar venta para nota de débito
     */
    public function validarVenta(string $ventaId): JsonResponse
    {
        try {
            $resultado = $this->notaDebitoService->validarVentaParaNotaDebito($ventaId);

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
}
