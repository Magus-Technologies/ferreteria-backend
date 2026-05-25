<?php

namespace App\Http\Controllers\Entrega;

use App\Http\Controllers\Controller;
use App\Models\EstadoEntrega;
use App\Models\QuienEntrega;
use App\Models\TipoDespacho;
use App\Models\TipoEntrega;
use Illuminate\Http\JsonResponse;

class EntregaCatalogosController extends Controller
{
    public function tiposEntrega(): JsonResponse
    {
        return response()->json(
            TipoEntrega::activo()->get(['id', 'codigo', 'nombre', 'icono', 'color', 'orden'])
        );
    }

    public function tiposDespacho(): JsonResponse
    {
        return response()->json(
            TipoDespacho::orderBy('id')->get(['id', 'codigo', 'nombre'])
        );
    }

    public function estadosEntrega(): JsonResponse
    {
        return response()->json(
            EstadoEntrega::ordenado()->get(['id', 'codigo', 'nombre', 'color', 'es_final'])
        );
    }

    public function quienesEntrega(): JsonResponse
    {
        return response()->json(
            QuienEntrega::orderBy('id')->get(['id', 'codigo', 'nombre'])
        );
    }
}
