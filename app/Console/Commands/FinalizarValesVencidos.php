<?php

namespace App\Console\Commands;

use App\Events\ModelChanged;
use App\Models\ValeCompra;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FinalizarValesVencidos extends Command
{
    protected $signature = 'vales:finalizar-vencidos';

    protected $description = 'Marca como FINALIZADO los vales de compra cuya fecha_fin ya pasó';

    public function handle(): int
    {
        $hoy = Carbon::today();

        $this->info('Buscando vales vencidos...');

        // Solo vales con fecha_fin definida y ya pasada, que aún figuran ACTIVO/PAUSADO.
        // Los de fecha_fin = null no vencen (vigencia indefinida).
        $vales = ValeCompra::whereIn('estado', ['ACTIVO', 'PAUSADO'])
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', $hoy)
            ->get();

        if ($vales->isEmpty()) {
            $this->info('No se encontraron vales vencidos.');
            return self::SUCCESS;
        }

        $this->info("Se encontraron {$vales->count()} vale(s) vencido(s).");

        foreach ($vales as $vale) {
            $vale->update(['estado' => 'FINALIZADO']);

            Log::info('[FinalizarValesVencidos] Vale finalizado por vencimiento', [
                'vale_id' => $vale->id,
                'codigo' => $vale->codigo,
                'fecha_fin' => optional($vale->fecha_fin)->toDateString(),
            ]);

            $this->line("   {$vale->codigo} - {$vale->nombre} finalizado (venció: " . optional($vale->fecha_fin)->toDateString() . ')');
        }

        // Avisar a los clientes conectados para que refresquen las listas en vivo.
        // Best effort: si Reverb está caído, no debe romper el comando.
        try {
            event(new ModelChanged(module: 'vales-compra', action: 'updated'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast vales-compra falló (¿Reverb caído?): ' . $e->getMessage());
        }

        $this->info("Proceso completado: {$vales->count()} vale(s) finalizado(s).");
        Log::info("[FinalizarValesVencidos] Proceso completado: {$vales->count()} vale(s) finalizado(s).");

        return self::SUCCESS;
    }
}
