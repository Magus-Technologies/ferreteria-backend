<?php

namespace App\Http\Controllers\FacturacionElectronica;

use App\DTOs\FacturacionElectronica\FacturaDTO;
use App\Http\Controllers\Controller;
use App\Models\ComprobanteElectronico;
use App\Services\Interfaces\FacturaServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FacturaController extends Controller
{
    public function __construct(
        private FacturaServiceInterface $facturaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['estado_sunat', 'fecha_inicio', 'fecha_fin', 'serie', 'search']);
            $porPagina = $request->input('per_page', 15);

            if ($request->has('paginated') && $request->boolean('paginated')) {
                $facturas = $this->facturaService->listarPaginado($filtros, $porPagina);
                return response()->json([
                    'success' => true,
                    'data' => $facturas->items(),
                    'pagination' => [
                        'total' => $facturas->total(),
                        'per_page' => $facturas->perPage(),
                        'current_page' => $facturas->currentPage(),
                        'last_page' => $facturas->lastPage(),
                    ],
                ]);
            }

            $facturas = $this->facturaService->listar($filtros);
            return response()->json(['success' => true, 'data' => $facturas]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function generarComprobante(Request $request): JsonResponse
    {
        try {
            $request->validate(['venta_id' => 'required|string|exists:venta,id']);
            
            $dto = new FacturaDTO(
                ventaId: $request->input('venta_id'),
                usuarioId: auth()->id()
            );

            $resultado = $this->facturaService->generarComprobanteDesdeVenta($dto);
            return response()->json($resultado, $resultado['success'] ? 201 : 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(string $ventaId): JsonResponse
    {
        try {
            $factura = $this->facturaService->obtenerPorVentaId($ventaId);
            if (!$factura) {
                return response()->json(['success' => false, 'message' => 'Factura no encontrada'], 404);
            }
            return response()->json(['success' => true, 'data' => $factura]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function enviarSunat(string $ventaId): JsonResponse
    {
        try {
            $resultado = $this->facturaService->enviarASunat($ventaId, 'manual');
            return response()->json(['success' => true, 'message' => $resultado['mensaje'] ?? 'Enviado', 'data' => $resultado]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function consultarEstadoSunat(string $ventaId): JsonResponse
    {
        try {
            $resultado = $this->facturaService->consultarEstadoSunat($ventaId);
            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function verXml(string $ventaId): Response
    {
        try {
            $xml = $this->facturaService->obtenerXml($ventaId);
            return response($xml, 200)->header('Content-Type', 'application/xml')->header('Content-Disposition', 'inline');
        } catch (\Exception $e) {
            return response('Error: ' . $e->getMessage(), 404)->header('Content-Type', 'text/plain');
        }
    }

    public function verXmlPorComprobante(string $comprobanteId): Response
    {
        try {
            $comprobante = ComprobanteElectronico::findOrFail($comprobanteId);
            
            if (!$comprobante->xml_firmado && !$comprobante->xml_path) {
                return response('XML no disponible', 404)->header('Content-Type', 'text/plain');
            }

            // Si tiene xml_firmado en la BD, devolverlo directamente
            if ($comprobante->xml_firmado) {
                return response($comprobante->xml_firmado, 200)
                    ->header('Content-Type', 'application/xml')
                    ->header('Content-Disposition', 'inline');
            }

            // Si no, leer del archivo
            $xmlPath = storage_path('app/' . $comprobante->xml_path);
            if (!file_exists($xmlPath)) {
                return response('Archivo XML no encontrado', 404)->header('Content-Type', 'text/plain');
            }

            $xml = file_get_contents($xmlPath);
            return response($xml, 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', 'inline');
        } catch (\Exception $e) {
            return response('Error: ' . $e->getMessage(), 404)->header('Content-Type', 'text/plain');
        }
    }

    public function descargarCdr(string $ventaId): Response
    {
        try {
            $cdr = $this->facturaService->obtenerCdr($ventaId);
            $factura = $this->facturaService->obtenerPorVentaId($ventaId);
            $venta = $factura['venta'];
            $tipoDoc = $venta->tipo_documento === '01' ? '01' : '03';
            $nombreArchivo = "R-" . config('greenter.ruc') . "-{$tipoDoc}-{$venta->serie}-{$venta->numero}.xml";
            return response($cdr, 200)->header('Content-Type', 'application/xml')->header('Content-Disposition', "attachment; filename=\"{$nombreArchivo}\"");
        } catch (\Exception $e) {
            return response('Error: ' . $e->getMessage(), 404)->header('Content-Type', 'text/plain');
        }
    }

    public function validarVenta(string $ventaId): JsonResponse
    {
        try {
            $resultado = $this->facturaService->validarVentaParaFacturacion($ventaId);
            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
