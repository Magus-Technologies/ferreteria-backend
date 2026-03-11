<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AperturaCierreCaja;
use Carbon\Carbon;

class BorrarAperturaHoy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apertura:borrar-hoy';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Borrar la apertura de hoy para testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hoy = Carbon::now()->toDateString();
        
        $this->info("🗑️  Borrando aperturas de hoy ({$hoy})...");
        
        $deleted = AperturaCierreCaja::whereDate('fecha_apertura', $hoy)
            ->where('estado', 'abierta')
            ->delete();
        
        $this->info("✅ Se borraron {$deleted} apertura(s) de hoy");
        
        // Mostrar aperturas restantes
        $aperturas = AperturaCierreCaja::orderBy('fecha_apertura', 'desc')
            ->limit(5)
            ->get(['id', 'caja_principal_id', 'monto_apertura', 'fecha_apertura', 'estado']);
        
        $this->info("\n📋 Últimas aperturas:");
        $this->table(
            ['ID', 'Caja', 'Monto', 'Fecha', 'Estado'],
            $aperturas->map(fn($a) => [
                $a->id,
                $a->caja_principal_id,
                $a->monto_apertura,
                $a->fecha_apertura->format('Y-m-d H:i:s'),
                $a->estado,
            ])->toArray()
        );
        
        // Verificar si hay apertura de hoy
        $aperturaHoy = AperturaCierreCaja::whereDate('fecha_apertura', $hoy)->first();
        
        if ($aperturaHoy) {
            $this->warn("⚠️  Aún hay apertura de hoy: {$aperturaHoy->id}");
        } else {
            $this->info("✅ No hay apertura de hoy - El modal debería aparecer");
        }
    }
}
