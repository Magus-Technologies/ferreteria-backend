<?php

namespace App\Support;

use App\Models\RequerimientoInterno;
use Carbon\Carbon;

class BloqueMantenimientoCalculator
{
    /**
     * Calcula el rango [fecha_inicio, fecha_fin] del bloqueo de calendario
     * derivado de los servicios de un requerimiento. Si no hay servicios con
     * fecha, cae al nivel del formulario (fecha_requerida + duracion del
     * requerimiento). Si no hay forma de calcularlo, retorna null para que
     * el caller decida cómo manejarlo (NO usa now() como fallback silencioso).
     *
     * @return array{fecha_inicio: Carbon, fecha_fin: Carbon}|null
     */
    public static function calcular(RequerimientoInterno $requerimiento): ?array
    {
        $servicios = $requerimiento->servicios;
        $fechaInicio = null;
        $fechaFin = null;

        if ($servicios && $servicios->count() > 0) {
            foreach ($servicios as $srv) {
                if (!$srv->fecha_inicio_estimada) {
                    continue;
                }

                $inicio = Carbon::parse($srv->fecha_inicio_estimada);
                $fin = self::aplicarDuracion(
                    $inicio->copy(),
                    $srv->duracion_cantidad,
                    $srv->duracion_unidad
                );

                if ($fechaInicio === null || $inicio->lt($fechaInicio)) {
                    $fechaInicio = $inicio;
                }
                if ($fechaFin === null || $fin->gt($fechaFin)) {
                    $fechaFin = $fin;
                }
            }
        }

        if ($fechaInicio !== null && $fechaFin !== null) {
            return ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin];
        }

        // Fallback: usar fecha_requerida + duracion del formulario, sólo si existe.
        if ($requerimiento->fecha_requerida) {
            $inicio = Carbon::parse($requerimiento->fecha_requerida);
            $fin = self::aplicarDuracion(
                $inicio->copy(),
                $requerimiento->duracion_cantidad,
                $requerimiento->duracion_unidad
            );
            return ['fecha_inicio' => $inicio, 'fecha_fin' => $fin];
        }

        return null;
    }

    private static function aplicarDuracion(Carbon $base, $cantidad, ?string $unidad): Carbon
    {
        if (!$cantidad || !$unidad) {
            return $base->copy()->addHours(2);
        }

        $cant = (int) $cantidad;
        $uni = strtolower($unidad);

        return match (true) {
            $uni === 'dias' || $uni === 'día' || $uni === 'dia' => $base->copy()->addDays(max($cant - 1, 0))->endOfDay(),
            $uni === 'horas' || $uni === 'hora' => $base->copy()->addHours($cant),
            $uni === 'minutos' || $uni === 'minuto' => $base->copy()->addMinutes($cant),
            default => $base->copy()->addHours(2),
        };
    }
}
