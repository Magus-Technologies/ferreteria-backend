<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClearKardexSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar la tabla kardex_inventarios
        DB::table('kardex_facturacions')->truncate();
        DB::table('kardex_inventarios')->truncate();
        
        $this->command->info('Tabla kardex_inventarios limpiada exitosamente.');
    }
}
