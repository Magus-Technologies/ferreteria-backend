<?php

namespace App\Http\Controllers;

use App\Models\Distrito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistritoController extends Controller
{
    /**
     * Obtener todos los distritos (opcionalmente filtrados por provincia)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Distrito::distritos();

        // Filtrar por provincia y departamento
        if ($request->has('departamento') && $request->has('provincia')) {
            $query->porProvincia($request->departamento, $request->provincia);
        }

        // Buscar por nombre
        if ($request->has('search')) {
            $query->where('nombre', 'like', '%' . $request->search . '%');
        }

        $distritos = $query->get([
            'id_ubigeo',
            'departamento',
            'provincia',
            'distrito',
            'nombre'
        ]);

        return response()->json(['data' => $distritos]);
    }

    /**
     * Obtener un distrito por ID
     */
    public function show($id): JsonResponse
    {
        $distrito = Distrito::distritos()
            ->where('id_ubigeo', $id)
            ->firstOrFail();

        return response()->json(['data' => $distrito]);
    }
}
