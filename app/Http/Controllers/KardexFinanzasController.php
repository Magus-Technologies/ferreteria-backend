<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\KardexFinanzasService;

/**
 * Controlador específico para el Kardex de Finanzas.
 * Utiliza inyección de dependencias para delegar la lógica al servicio correspondiente.
 */
class KardexFinanzasController extends Controller
{
    /**
     * @var KardexFinanzasService
     */
    protected $kardexService;

    /**
     * Constructor con inyección del servicio.
     */
    public function __construct(KardexFinanzasService $kardexService)
    {
        $this->kardexService = $kardexService;
    }

    /**
     * Endpoint principal para obtener el Kardex de Finanzas.
     * GET /api/kardex/finanzas
     */
    public function index(Request $request)
    {
        $request->validate([
            'metodo_pago_id' => 'nullable|string',
            'sub_caja_id' => 'nullable|string',
            'vendedor_id' => 'nullable|string',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'per_page' => 'nullable|integer|min:-1|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        // Recolectar filtros
        $filters = $request->only([
            'metodo_pago_id', 
            'sub_caja_id', 
            'vendedor_id', 
            'desde', 
            'hasta', 
            'per_page', 
            'page'
        ]);

        // Delegar ejecución al servicio
        $result = $this->kardexService->getKardex($filters);

        return response()->json($result);
    }
}
