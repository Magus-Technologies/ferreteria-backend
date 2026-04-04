<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class CumpleanosController extends Controller
{
    /**
     * Obtener usuarios y clientes con cumpleaños próximos (hoy, 3 días, 7 días)
     */
    public function proximos(\Illuminate\Http\Request $request): JsonResponse
    {
        $hoy = Carbon::today();
        // Máximo 30 días, por defecto 7
        $diasMaximos = (int) $request->query('dias', 7);
        $diasMaximos = max(0, min(30, $diasMaximos));

        $resultados = [];

        // Usuarios
        $usuarios = User::where('estado', true)
            ->whereNotNull('fecha_nacimiento')
            ->get(['id', 'name', 'fecha_nacimiento', 'image']);

        foreach ($usuarios as $usuario) {
            $cumple = $this->calcularCumpleanos($usuario->fecha_nacimiento, $hoy, $diasMaximos);
            if ($cumple) {
                $resultados[] = [
                    'id' => $usuario->id,
                    'nombre' => $usuario->name,
                    'imagen' => $usuario->image,
                    'fecha_nacimiento' => $cumple['fecha_nacimiento'],
                    'dias_restantes' => $cumple['dias_restantes'],
                    'edad' => $cumple['edad'],
                    'tipo' => $cumple['tipo'],
                    'entidad_tipo' => 'usuario',
                ];
            }
        }

        // Clientes
        $clientes = Cliente::whereNotNull('fecha_nacimiento')
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'fecha_nacimiento']);

        foreach ($clientes as $cliente) {
            $cumple = $this->calcularCumpleanos($cliente->fecha_nacimiento, $hoy, $diasMaximos);
            if ($cumple) {
                $nombre = $cliente->razon_social
                    ?: trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? ''))
                    ?: 'Cliente';
                $resultados[] = [
                    'id' => $cliente->id,
                    'nombre' => $nombre,
                    'imagen' => null,
                    'fecha_nacimiento' => $cumple['fecha_nacimiento'],
                    'dias_restantes' => $cumple['dias_restantes'],
                    'edad' => $cumple['edad'],
                    'tipo' => $cumple['tipo'],
                    'entidad_tipo' => 'cliente',
                ];
            }
        }

        // Ordenar: primero hoy, luego por días restantes
        usort($resultados, fn($a, $b) => $a['dias_restantes'] <=> $b['dias_restantes']);

        return response()->json(['data' => $resultados]);
    }

    private function calcularCumpleanos($fechaNacimiento, Carbon $hoy, int $diasMaximos): ?array
    {
        $nacimiento = Carbon::parse($fechaNacimiento);
        $cumpleEsteAnio = $nacimiento->copy()->year($hoy->year);

        if ($cumpleEsteAnio->lt($hoy)) {
            $cumpleEsteAnio->addYear();
        }

        $diasRestantes = (int) $hoy->diffInDays($cumpleEsteAnio);

        if ($diasRestantes < 0 || $diasRestantes > $diasMaximos) {
            return null;
        }

        $tipo = 'proximo';
        if ($diasRestantes === 0) {
            $tipo = 'hoy';
        } elseif ($diasRestantes <= 3) {
            $tipo = '3dias';
        }

        $edad = $hoy->year - $nacimiento->year;
        if ($diasRestantes > 0) {
            $edad--;
        }

        return [
            'fecha_nacimiento' => $nacimiento->format('d/m'),
            'dias_restantes' => $diasRestantes,
            'edad' => $edad + 1,
            'tipo' => $tipo,
        ];
    }
}
