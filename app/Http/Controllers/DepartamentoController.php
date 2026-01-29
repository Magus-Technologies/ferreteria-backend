<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    /**
     * Obtener todos los departamentos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Departamento::departamentos();

        // Buscar por nombre
        if ($request->has('search')) {
            $query->where('nombre', 'like', '%' . $request->search . '%');
        }

        $departamentos = $query->get([
            'id_ubigeo',
            'departamento',
            'nombre'
        ]);

        return response()->json(['data' => $departamentos]);
    }

    /**
     * Obtener un departamento por ID
     */
    public function show($id): JsonResponse
    {
        $departamento = Departamento::departamentos()
            ->where('id_ubigeo', $id)
            ->firstOrFail();

        return response()->json(['data' => $departamento]);
    }

    /**
     * Obtener provincias de un departamento
     */
    public function provincias($codigo): JsonResponse
    {
        $departamento = Departamento::departamentos()
            ->where('departamento', $codigo)
            ->firstOrFail();

        $provincias = $departamento->provincias()
            ->get([
                'id_ubigeo',
                'departamento',
                'provincia',
                'nombre'
            ]);

        return response()->json(['data' => $provincias]);
    }
}
