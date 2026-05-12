<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductoAlmacen;

class InitializeDualCostTrackingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Inicializa los campos de costo dual para registros existentes
     */
    public function run(): void
    {
        // Actualizar todos los ProductoAlmacen existentes
        // Establecer costo_actual = costo actual
        // Establecer stock_costo_actual = stock_fraccion actual
        // Dejar costo_anterior y stock_costo_anterior en null/0
        
        ProductoAlmacen::whereNull('costo_actual')->update([
            'costo_actual' => \DB::raw('costo'),
            'stock_costo_actual' => \DB::raw('stock_fraccion'),
            'costo_anterior' => null,
            'stock_costo_anterior' => 0,
        ]);

        $this->command->info('Inicialización de costo dual completada.');
    }
}
