<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEntrega;
use App\Models\Role;
use App\Traits\BroadcastsModelChanges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionEntregaController extends Controller
{
    use BroadcastsModelChanges;
    public function index(): JsonResponse
    {
        $configs = ConfiguracionEntrega::whereIn('clave', ['roles_entrega_tienda', 'roles_supervisores_entrega'])
            ->get()
            ->keyBy('clave');

        return response()->json([
            'roles_entrega_tienda'        => $configs->get('roles_entrega_tienda')?->valor        ?? ['ALMACENERO'],
            'roles_supervisores_entrega'  => $configs->get('roles_supervisores_entrega')?->valor  ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $rolesValidos = Role::where('name', '!=', 'admin_global')
            ->pluck('name')
            ->values()
            ->toArray();

        $inRule = 'in:' . implode(',', $rolesValidos);

        $validated = $request->validate([
            'roles_entrega_tienda'          => ['required', 'array'],
            'roles_entrega_tienda.*'        => ['string', $inRule],
            'roles_supervisores_entrega'    => ['sometimes', 'array'],
            'roles_supervisores_entrega.*'  => ['string', $inRule],
        ]);

        ConfiguracionEntrega::updateOrCreate(
            ['clave' => 'roles_entrega_tienda'],
            ['valor' => $validated['roles_entrega_tienda']]
        );

        ConfiguracionEntrega::updateOrCreate(
            ['clave' => 'roles_supervisores_entrega'],
            ['valor' => $validated['roles_supervisores_entrega'] ?? []]
        );

        $this->broadcastChange('configuracion-entrega', 'updated');

        return response()->json(['success' => true]);
    }
}
