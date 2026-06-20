<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEntrega;
use App\Traits\BroadcastsModelChanges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionEntregaController extends Controller
{
    use BroadcastsModelChanges;
    public function index(): JsonResponse
    {
        $config = ConfiguracionEntrega::where('clave', 'roles_entrega_tienda')->first();

        return response()->json([
            'roles_entrega_tienda' => $config?->valor ?? ['ALMACENERO'],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'roles_entrega_tienda'   => ['required', 'array'],
            'roles_entrega_tienda.*' => ['string', 'in:ADMINISTRADOR,VENDEDOR,ALMACENERO,CONTADOR,DESPACHADOR,CONDUCTOR'],
        ]);

        ConfiguracionEntrega::updateOrCreate(
            ['clave' => 'roles_entrega_tienda'],
            ['valor' => $validated['roles_entrega_tienda']]
        );

        $this->broadcastChange('configuracion-entrega', 'updated');

        return response()->json(['success' => true]);
    }
}
