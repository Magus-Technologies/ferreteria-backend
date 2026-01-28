<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Restriction;

class RestrictionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Este seeder está vacío por diseño.
     * En el nuevo sistema de restricciones (lista negra), por defecto todos tienen acceso a todo.
     * Solo se guardan restricciones cuando el admin las asigna desde el configurador visual.
     *
     * Si necesitas crear restricciones predefinidas, descomenta el código de ejemplo:
     */
    public function run(): void
    {
        // Ejemplo: crear restricciones predefinidas
        /*
        $restrictions = [
            [
                'name' => 'facturacion-electronica.crear-guia.index',
                'descripcion' => 'Acceso a crear guías de remisión',
            ],
            [
                'name' => 'facturacion-electronica.mis-ventas.button-exportar',
                'descripcion' => 'Botón exportar en Mis Ventas',
            ],
        ];

        foreach ($restrictions as $restriction) {
            Restriction::firstOrCreate(
                ['name' => $restriction['name']],
                ['descripcion' => $restriction['descripcion']]
            );
        }
        */
    }
}
