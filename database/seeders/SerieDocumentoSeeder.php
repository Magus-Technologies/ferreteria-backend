<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SerieDocumentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'tipo_documento' => '01', // Factura
                'serie' => 'F001',
                'correlativo' => 0,
                'almacen_id' => 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tipo_documento' => '03', // Boleta
                'serie' => 'B001',
                'correlativo' => 0,
                'almacen_id' => 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tipo_documento' => 'nv', // Nota de Venta
                'serie' => 'NV01',
                'correlativo' => 0,
                'almacen_id' => 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($data as $item) {
            DB::table('seriedocumento')->updateOrInsert(
                [
                    'tipo_documento' => $item['tipo_documento'],
                    'serie' => $item['serie'],
                    'almacen_id' => $item['almacen_id'],
                ],
                $item
            );
        }

        $this->command->info('Serie Documento seeder ejecutado exitosamente.');
    }
}
