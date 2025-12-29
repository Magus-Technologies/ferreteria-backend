<?php

namespace App\Http\Controllers;

use App\Models\DespliegueDePago;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DespliegueDePagoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DespliegueDePago::query();

        // Filter by mostrar if provided
        if ($request->has('mostrar')) {
            $query->where('mostrar', $request->boolean('mostrar'));
        }

        $items = $query->get();

        return response()->json([
            'data' => $items
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $item = DespliegueDePago::findOrFail($id);
        
        return response()->json([
            'data' => $item
        ]);
    }
}
