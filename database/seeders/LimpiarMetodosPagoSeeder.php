<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LimpiarMetodosPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Deshabilitar verificación de claves foráneas temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Limpiar tablas en orden correcto (de hijos a padres)
        DB::table('desplieguedepagoventa')->truncate();
        DB::table('numeros_operacion_pago')->truncate();
        DB::table('desplieguedepago')->truncate();
        DB::table('metododepago')->truncate();

        // Rehabilitar verificación de claves foráneas
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('✅ Tablas de métodos de pago limpiadas exitosamente');
    }
}
