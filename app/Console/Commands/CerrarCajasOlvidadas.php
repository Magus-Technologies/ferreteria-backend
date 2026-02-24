<?php

namespace App\Console\Commands;

use App\Models\AperturaCierreCaja;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CerrarCajasOlvidadas extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cajas:cerrar-olvidadas';

    /**
     * The console command description.
     */
    protected $description = 'Cierra automáticamente las cajas que quedaron abiertas del día anterior con estado "no_realizado"';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Buscando cajas olvidadas...');

        // Buscar cajas abiertas cuya fecha_apertura sea anterior a hoy
        $cajasOlvidadas = AperturaCierreCaja::where('estado', 'abierta')
            ->whereDate('fecha_apertura', '<', Carbon::today())
            ->whereNull('fecha_cierre')
            ->get();

        if ($cajasOlvidadas->isEmpty()) {
            $this->info('No se encontraron cajas olvidadas.');
            Log::info('[CerrarCajasOlvidadas] No se encontraron cajas olvidadas.');
            return self::SUCCESS;
        }

        $this->info("Se encontraron {$cajasOlvidadas->count()} caja(s) olvidada(s).");

        $cerradas = 0;

        foreach ($cajasOlvidadas as $apertura) {
            try {
                $apertura->update([
                    'estado' => 'cerrada',
                    'estado_cierre' => 'no_realizado',
                    'fecha_cierre' => now(),
                    'comentarios' => 'Cierre automático - No se realizó el cierre del día ' . Carbon::parse($apertura->fecha_apertura)->format('d/m/Y'),
                ]);

                $cerradas++;

                Log::info('[CerrarCajasOlvidadas] Caja cerrada automáticamente', [
                    'apertura_id' => $apertura->id,
                    'user_id' => $apertura->user_id,
                    'fecha_apertura' => $apertura->fecha_apertura,
                ]);

                $this->line("   Caja {$apertura->id} cerrada (abierta el {$apertura->fecha_apertura})");
            } catch (\Exception $e) {
                Log::error('[CerrarCajasOlvidadas] Error al cerrar caja', [
                    'apertura_id' => $apertura->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("   Error al cerrar caja {$apertura->id}: {$e->getMessage()}");
            }
        }

        $this->info("Proceso completado: {$cerradas}/{$cajasOlvidadas->count()} cajas cerradas.");
        Log::info("[CerrarCajasOlvidadas] Proceso completado: {$cerradas} cajas cerradas.");

        return self::SUCCESS;
    }
}
