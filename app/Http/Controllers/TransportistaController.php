<?php

namespace App\Http\Controllers;

use App\Models\Transportista;
use App\Services\MtcConsultaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransportistaController extends Controller
{
    /**
     * Consultar el Registro Nacional de Transporte de Mercancías (MTC) por RUC.
     *
     * Devuelve los registros habilitados del transportista (código MTC +
     * razón social). Un RUC puede tener varios registros; el frontend usa
     * el primero para autocompletar N° Registro MTC. Respuesta cacheada 24h.
     */
    public function consultaMtc(string $ruc, MtcConsultaService $mtc)
    {
        return response()->json([
            'data' => $mtc->consultarPorRuc($ruc),
        ]);
    }

    /**
     * Listar transportistas con búsqueda y paginación.
     *
     * Por defecto trae activos e inactivos (el catálogo de administración
     * necesita ver ambos para poder reactivar). El select de guía de
     * remisión filtra solo activos con `solo_activos=1`.
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

        if ($request->boolean('solo_activos')) {
            $query->where('estado', 1);
        }

        $perPage = $request->get('per_page', 20);

        $transportistas = $query->orderBy('razon_social', 'asc')
            ->paginate($perPage);

        return response()->json($transportistas);
    }

    /**
     * Crear (o actualizar por RUC) un transportista.
     *
     * No se envía 'estado' aquí a propósito: en un alta nuevo la columna
     * toma su default (activo) y en un upsert por RUC ya existente no se
     * toca el estado (no reactiva ni desactiva algo que ya estaba así).
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

    /**
     * Actualizar un transportista existente.
     *
     * Usa 'sometimes' en ruc/razon_social para soportar tanto la edición
     * completa (modal editar) como el toggle rápido de estado desde la
     * tabla (payload parcial {estado: true|false}), mirror del patrón de
     * ChoferController::update pero flexible para no reventar el toggle.
     */
    public function update(Request $request, $id)
    {
        $transportista = Transportista::find($id);

        if (!$transportista) {
            return response()->json([
                'message' => 'Transportista no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'ruc' => [
                'sometimes',
                'required',
                'digits:11',
                Rule::unique('transportista', 'ruc')->ignore($id),
            ],
            'razon_social' => 'sometimes|required|string|max:255',
            'nro_mtc' => 'nullable|string|max:50',
            'estado' => 'sometimes|boolean',
        ]);

        $transportista->fill($validated)->save();

        return response()->json([
            'message' => 'Transportista actualizado exitosamente',
            'data' => $transportista->fresh(),
        ]);
    }
}
