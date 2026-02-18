<?php

namespace App\Http\Controllers;

use App\Http\Resources\GananciasResource;
use App\Services\Interfaces\GananciasServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GananciasController extends Controller
{
    public function __construct(
        private GananciasServiceInterface $gananciasService
    ) {}

    /**
     * Obtener reporte de ganancias con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'required|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'cliente_id' => 'sometimes|integer',
            'search' => 'sometimes|string', // Búsqueda de cliente por texto
            'user_id' => 'sometimes|string',
            'producto_servicio' => 'sometimes|string',
            'marca' => 'sometimes|string',
            'forma_pago' => 'sometimes|string',
            'tipo_doc' => 'sometimes|string',
            'serie' => 'sometimes|string',
            'numero' => 'sometimes|integer',
            'sucursal' => 'sometimes|string',
            'confirmar_caja' => 'sometimes|string',
            'mostrar_hora' => 'sometimes|string',
            'incluir' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $filtros = $request->only([
            'almacen_id', 'desde', 'hasta', 'cliente_id', 'search', 'user_id',
            'producto_servicio', 'marca', 'forma_pago', 'tipo_doc', 'serie',
            'numero', 'sucursal', 'confirmar_caja', 'mostrar_hora', 'incluir'
        ]);

        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        $resultado = $this->gananciasService->obtenerReporteGanancias($filtros, $perPage, $page);

        return response()->json([
            'data' => GananciasResource::collection($resultado['data']),
            'resumen' => $resultado['resumen'],
            'pagination' => $resultado['pagination']
        ]);
    }

    /**
     * Obtener resumen de ganancias (para las cards)
     */
    public function resumen(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'required|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'cliente_id' => 'sometimes|integer',
            'search' => 'sometimes|string',
            'user_id' => 'sometimes|string',
            'producto_servicio' => 'sometimes|string',
            'marca' => 'sometimes|string',
            'forma_pago' => 'sometimes|string',
            'tipo_doc' => 'sometimes|string',
            'serie' => 'sometimes|string',
            'numero' => 'sometimes|integer',
            'sucursal' => 'sometimes|string',
        ]);

        $filtros = $request->only([
            'almacen_id', 'desde', 'hasta', 'cliente_id', 'search', 'user_id',
            'producto_servicio', 'marca', 'forma_pago', 'tipo_doc', 'serie',
            'numero', 'sucursal'
        ]);

        $resumen = $this->gananciasService->obtenerResumenGanancias($filtros);

        return response()->json([
            'data' => $resumen
        ]);
    }

    /**
     * Exportar reporte de ganancias a Excel
     */
    public function exportar(Request $request): JsonResponse
    {
        $request->validate([
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'cliente_id' => 'sometimes|integer',
            'search' => 'sometimes|string',
            'user_id' => 'sometimes|string',
            'formato' => 'required|in:excel,pdf',
        ]);

        $filtros = $request->only([
            'almacen_id', 'desde', 'hasta', 'cliente_id', 'search', 'user_id'
        ]);

        $formato = $request->get('formato', 'excel');

        $archivo = $this->gananciasService->exportarReporte($filtros, $formato);

        return response()->json([
            'data' => [
                'url' => $archivo['url'],
                'nombre' => $archivo['nombre']
            ]
        ]);
    }

    /**
     * Enviar reporte por correo
     */
    public function enviarCorreo(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'almacen_id' => 'sometimes|integer',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date',
            'cliente_id' => 'sometimes|integer',
            'search' => 'sometimes|string',
            'user_id' => 'sometimes|string',
        ]);

        $email = $request->get('email');
        $filtros = $request->only([
            'almacen_id', 'desde', 'hasta', 'cliente_id', 'search', 'user_id'
        ]);

        $this->gananciasService->enviarReportePorCorreo($email, $filtros);

        return response()->json([
            'message' => 'Reporte enviado por correo exitosamente'
        ]);
    }
}