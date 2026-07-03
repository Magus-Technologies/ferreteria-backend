<?php

namespace App\Http\Controllers;

use App\Models\Transportista;
use Illuminate\Http\Request;

class TransportistaController extends Controller
{
    /**
     * Listar transportistas con búsqueda y paginación
     */
    public function index(Request $request)
    {
        $query = Transportista::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ruc', 'LIKE', "%{$search}%")
                  ->orWhere('razon_social', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);

        $transportistas = $query->orderBy('razon_social', 'asc')
            ->paginate($perPage);

        return response()->json($transportistas);
    }

    /**
     * Crear (o actualizar por RUC) un transportista
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ruc' => 'required|digits:11',
            'razon_social' => 'required|string|max:255',
            'nro_mtc' => 'nullable|string|max:50',
        ]);

        $transportista = Transportista::updateOrCreate(
            ['ruc' => $validated['ruc']],
            [
                'razon_social' => $validated['razon_social'],
                'nro_mtc' => $validated['nro_mtc'] ?? null,
            ]
        );

        return response()->json($transportista, 201);
    }
}
