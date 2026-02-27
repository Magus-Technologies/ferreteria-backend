<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionNotificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ConfiguracionNotificacionController extends Controller
{
    /**
     * Obtener todas las configuraciones del usuario autenticado.
     * Si no existe una configuración para un tipo, devuelve los valores por defecto.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $guardadas = ConfiguracionNotificacion::where('user_id', $user->id)
            ->get()
            ->keyBy('tipo');

        // Devolver todos los tipos con sus valores (guardados o por defecto)
        $resultado = collect(ConfiguracionNotificacion::tipos())->map(function ($tipo) use ($guardadas) {
            if ($guardadas->has($tipo)) {
                return $guardadas[$tipo];
            }

            // Valores por defecto
            return [
                'id'               => null,
                'user_id'          => null,
                'tipo'             => $tipo,
                'habilitado'       => true,
                'dias_anticipacion' => 7,
                'extra'            => null,
            ];
        });

        return response()->json(['data' => $resultado]);
    }

    /**
     * Guardar o actualizar la configuración de un tipo para el usuario autenticado.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo'             => 'required|string|in:' . implode(',', ConfiguracionNotificacion::tipos()),
            'habilitado'       => 'required|boolean',
            'dias_anticipacion' => 'required|integer|min:0|max:30',
            'extra'            => 'nullable|array',
        ]);

        $user = Auth::user();

        $config = ConfiguracionNotificacion::where('user_id', $user->id)
            ->where('tipo', $validated['tipo'])
            ->first();

        if ($config) {
            $config->update([
                'habilitado'        => $validated['habilitado'],
                'dias_anticipacion' => $validated['dias_anticipacion'],
                'extra'             => $validated['extra'] ?? null,
            ]);
        } else {
            $config = ConfiguracionNotificacion::create([
                'id'               => (string) Str::ulid(),
                'user_id'          => $user->id,
                'tipo'             => $validated['tipo'],
                'habilitado'       => $validated['habilitado'],
                'dias_anticipacion' => $validated['dias_anticipacion'],
                'extra'            => $validated['extra'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Configuración guardada',
            'data'    => $config,
        ]);
    }

    /**
     * Obtener configuración de cumpleaños del usuario (para el BirthdayAlert).
     */
    public function getCumpleanos(): JsonResponse
    {
        $user = Auth::user();

        $config = ConfiguracionNotificacion::where('user_id', $user->id)
            ->where('tipo', 'cumpleanos')
            ->first();

        return response()->json([
            'data' => [
                'habilitado'        => $config?->habilitado ?? true,
                'dias_anticipacion' => $config?->dias_anticipacion ?? 7,
            ],
        ]);
    }
}
