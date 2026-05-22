<?php

namespace App\Http\Controllers;

use App\Models\VehiculoMantenimiento;
use App\Models\EntregaProducto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VehiculoDisponibilidadController extends Controller
{
    // GET /api/vehiculos/{id}/disponibilidad
    public function disponibilidad(Request $request, int $id): JsonResponse
    {
        $desde = $request->query('fecha_desde');
        $hasta = $request->query('fecha_hasta');
        $fecha = $request->query('fecha'); // Para verificación simple de disponibilidad

        // Si se proporciona una fecha específica, verificar disponibilidad
        if ($fecha) {
            $mantenimiento = VehiculoMantenimiento::with('requerimiento')
                ->where('vehiculo_id', $id)
                ->where('estado', 'aprobado')
                ->whereDate('fecha_inicio', '<=', $fecha)
                ->whereDate('fecha_fin', '>=', $fecha)
                ->first();

            if ($mantenimiento) {
                $requerimiento = $mantenimiento->requerimiento;
                return response()->json([
                    'disponible' => false,
                    'razon' => "El vehículo está en mantenimiento desde " . 
                        $mantenimiento->fecha_inicio->format('d/m/Y') . 
                        " hasta " . 
                        $mantenimiento->fecha_fin->format('d/m/Y'),
                    'mantenimiento' => [
                        'id' => $mantenimiento->id,
                        'tipo' => $mantenimiento->tipo,
                        'descripcion' => $mantenimiento->descripcion,
                        'fecha_inicio' => $mantenimiento->fecha_inicio->format('d/m/Y H:i'),
                        'fecha_fin' => $mantenimiento->fecha_fin->format('d/m/Y H:i'),
                        'estado' => $mantenimiento->estado,
                        'requerimiento' => $requerimiento ? [
                            'id' => $requerimiento->id,
                            'codigo' => $requerimiento->codigo,
                            'titulo' => $requerimiento->titulo,
                            'observaciones' => $requerimiento->observaciones,
                            'prioridad' => $requerimiento->prioridad,
                        ] : null,
                    ],
                ]);
            }

            return response()->json([
                'disponible' => true,
            ]);
        }

        // Entregas programadas para el vehiculo
        $entregas = EntregaProducto::where('vehiculo_id', $id)
            ->whereNotNull('fecha_programada')
            ->when($desde, fn($q) => $q->whereDate('fecha_programada', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('fecha_programada', '<=', $hasta))
            ->get()
            ->map(function ($e) {
                $start = $e->fecha_programada->copy();
                if ($e->hora_inicio) {
                    [$h, $m] = array_pad(explode(':', $e->hora_inicio), 2, 0);
                    $start->setTime((int) $h, (int) $m, 0);
                }

                if ($e->hora_fin) {
                    $end = $e->fecha_programada->copy();
                    [$h, $m] = array_pad(explode(':', $e->hora_fin), 2, 0);
                    $end->setTime((int) $h, (int) $m, 0);
                } else {
                    $end = $start->copy()->addMinutes(30);
                }

                return [
                    'tipo' => 'entrega',
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'meta' => ['id' => $e->id, 'venta_id' => $e->venta_id],
                ];
            });

        // Mantenimientos aprobados
        $mantenimientos = VehiculoMantenimiento::where('vehiculo_id', $id)
            ->where('estado', 'aprobado')
            ->when($desde, fn($q) => $q->where('fecha_fin', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('fecha_inicio', '<=', $hasta))
            ->get()
            ->map(function ($m) {
                return [
                    'tipo' => 'mantenimiento',
                    'start' => $m->fecha_inicio->toIso8601String(),
                    'end' => $m->fecha_fin->toIso8601String(),
                    'meta' => ['id' => $m->id, 'descripcion' => $m->descripcion],
                ];
            });

        $data = collect(array_merge($entregas->toArray(), $mantenimientos->toArray()));

        return response()->json(['data' => $data]);
    }

    // POST /api/vehiculos/{id}/mantenimientos
    public function store(Request $request, int $id)
    {
        $validated = $request->validate([
            'tipo' => 'required|string',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'estado' => 'nullable|string',
        ]);

        $m = VehiculoMantenimiento::create([
            'vehiculo_id' => $id,
            'tipo' => $validated['tipo'],
            'descripcion' => $validated['descripcion'] ?? null,
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'estado' => $validated['estado'] ?? 'pendiente',
            'created_by' => $request->user()->id ?? null,
        ]);

        return response()->json(['data' => $m], 201);
    }

    // GET /api/vehiculos/{id}/mantenimientos
    public function index(Request $request, int $id)
    {
        $items = VehiculoMantenimiento::where('vehiculo_id', $id)
            ->when($request->query('estado'), fn($q, $v) => $q->where('estado', $v))
            ->orderBy('fecha_inicio', 'asc')
            ->get();

        return response()->json(['data' => $items]);
    }
}
