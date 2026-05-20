<?php

namespace Database\Seeders;

use App\Models\CatalogoCargo;
use Illuminate\Database\Seeder;

class CatalogoCargoSeeder extends Seeder
{
    public function run(): void
    {
        $cargos = [
            ['codigo' => 'GERENTE GENERAL GERENCIA', 'descripcion' => 'Gerente General Gerencia', 'parent' => null, 'highlight' => true, 'staff' => false],
            ['codigo' => 'ADMINISTRADOR GERENCIA', 'descripcion' => 'Administrador Gerencia', 'parent' => 'GERENTE GENERAL GERENCIA', 'highlight' => false, 'staff' => true],
            ['codigo' => 'VENDEDOR', 'descripcion' => 'Vendedor', 'parent' => 'GERENTE GENERAL GERENCIA', 'highlight' => false, 'staff' => false],
            ['codigo' => 'ASISTENTE CONTABLE', 'descripcion' => 'Asistente Contable', 'parent' => 'GERENTE GENERAL GERENCIA', 'highlight' => false, 'staff' => false],
            ['codigo' => 'ALMACENERO', 'descripcion' => 'Almacenero', 'parent' => 'GERENTE GENERAL GERENCIA', 'highlight' => false, 'staff' => false],
            ['codigo' => 'CONDUCTOR MOTO-OBRERO', 'descripcion' => 'Conductor Moto-Obrero', 'parent' => 'GERENTE GENERAL GERENCIA', 'highlight' => false, 'staff' => false],
            ['codigo' => 'OBRERO-CONDUCTOR', 'descripcion' => 'Obrero-Conductor', 'parent' => 'CONDUCTOR MOTO-OBRERO', 'highlight' => false, 'staff' => false],
            ['codigo' => 'AYUDANTE DE CAMION', 'descripcion' => 'Ayudante de Camión', 'parent' => 'CONDUCTOR MOTO-OBRERO', 'highlight' => false, 'staff' => false],
        ];

        foreach ($cargos as $cargo) {
            CatalogoCargo::updateOrCreate(
                ['codigo' => $cargo['codigo']],
                [
                    'descripcion' => $cargo['descripcion'],
                    'parent' => $cargo['parent'],
                    'highlight' => $cargo['highlight'],
                    'staff' => $cargo['staff'],
                    'estado' => true,
                ]
            );
        }
    }
}
