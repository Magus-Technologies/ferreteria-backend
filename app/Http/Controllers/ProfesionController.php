<?php

namespace App\Http\Controllers;

use App\Models\Profesion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfesionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Profesion::query();

        if ($request->filled('search')) {
            $query->where('nombre', 'like', '%' . $request->search . '%');
        }

        return response()->json([
            'data' => $query->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:100|unique:profesion,nombre',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $profesion = Profesion::create($validator->validated());

        return response()->json([
            'data' => $profesion,
            'message' => 'Profesión creada exitosamente',
        ], 201);
    }
}
