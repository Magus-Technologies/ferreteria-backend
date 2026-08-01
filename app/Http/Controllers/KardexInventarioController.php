<?php

namespace App\Http\Controllers;

use App\Services\Kardex\KardexInventarioService;
use Illuminate\Http\Request;

class KardexInventarioController extends Controller
{
    public function __construct(private KardexInventarioService $service) {}

    public function index(Request $request)
    {
        $request->validate([
            'producto_id' => 'nullable|integer',
            'proveedor_id' => 'nullable|integer',
            'almacen_id' => 'nullable|integer',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'tipo' => 'nullable|string',
            'per_page' => 'nullable|integer|min:-1|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        return $this->service->getPaginated(
            $request->producto_id,
            $request->almacen_id,
            $request->desde,
            $request->hasta,
            $request->tipo,
            $request->per_page ?? 50,
            $request->page ?? 1,
            $request->proveedor_id
        );
    }
}
