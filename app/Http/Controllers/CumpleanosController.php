<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class CumpleanosController extends Controller
{
    /**
     * Obtener usuarios con cumpleaños próximos (hoy, 3 días, 7 días)
     */
    public function proximos(\Illuminate\Http\Request $request): JsonResponse
    {
        $hoy = Carbon::today();
        // Máximo 30 días, por defecto 7
        $diasMaximos = (int) $request->query('dias', 7);
        $diasMaximos = max(0, min(30, $diasMaximos));

        $usuarios = User::where('estado', true)
            ->whereNotNull('fecha_nacimiento')
            ->get(['id', 'name', 'fecha_nacimiento', 'image']);

        $resultados = [];

        foreach ($usuarios as $usuario) {
            $nacimiento = Carbon::parse($usuario->fecha_nacimiento);
            // Cumpleaños este año
            $cumpleEsteAnio = $nacimiento->copy()->year($hoy->year);
            // Si ya pasó, considerar el del próximo año
            if ($cumpleEsteAnio->lt($hoy)) {
                $cumpleEsteAnio->addYear();
            }

            $diasRestantes = (int) $hoy->diffInDays($cumpleEsteAnio);

            if ($diasRestantes >= 0 && $diasRestantes <= $diasMaximos) {
                $tipo = 'proximo';
                if ($diasRestantes === 0) {
                    $tipo = 'hoy';
                } elseif ($diasRestantes <= 3) {
                    $tipo = '3dias';
                }

                $edad = $hoy->year - $nacimiento->year;
                if ($diasRestantes > 0) {
                    $edad--; // Aún no cumple
                }

                $resultados[] = [
                    'id' => $usuario->id,
                    'nombre' => $usuario->name,
                    'imagen' => $usuario->image,
                    'fecha_nacimiento' => $nacimiento->format('d/m'),
                    'dias_restantes' => $diasRestantes,
                    'edad' => $edad + 1,
                    'tipo' => $tipo,
                ];
            }
        }

        // Ordenar: primero hoy, luego por días restantes
        usort($resultados, fn($a, $b) => $a['dias_restantes'] <=> $b['dias_restantes']);

        return response()->json(['data' => $resultados]);
    }
}
