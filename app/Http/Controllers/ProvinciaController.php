<?php

namespace App\Http\Controllers;

use App\Models\Provincia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvinciaController extends Controller
{
    /**
     * Obtener todas las provincias (opcionalmente filtradas por departamento)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Provincia::provincias();

        // Filtrar por departamento
        if ($request->has('departamento')) {
            $query->porDepartamento($request->departamento);
        }

        // Buscar por nombre
        if ($request->has('search')) {
            $query->where('nombre', 'like', '%' . $request->search . '%');
        }

        $provincias = $query->get([
            'id_ubigeo',
            'departamento',
            'provincia',
            'nombre'
        ]);

        return response()->json(['data' => $provincias]);
    }

    /**
     * Obtener una provincia por ID
     */
    public function show($id): JsonResponse
    {
        $provincia = Provincia::provincias()
            ->where('id_ubigeo', $id)
            ->firstOrFail();

        return response()->json(['data' => $provincia]);
    }

    /**
     * Obtener distritos de una provincia
     */
    public function distritos($departamento, $provincia): JsonResponse
    {
        $provinciaModel = Provincia::provincias()
            ->where('departamento', $departamento)
            ->where('provincia', $provincia)
            ->firstOrFail();

        $distritos = $provinciaModel->distritos()
            ->get([
                'id_ubigeo',
                'departamento',
                'provincia',
                'distrito',
                'nombre'
            ]);

        return response()->json(['data' => $distritos]);
    }
}
