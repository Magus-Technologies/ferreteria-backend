<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogosEntregaSeeder extends Seeder
{
    public function run(): void
    {
        // tipo_entrega
        DB::table('tipo_entrega')->upsert([
            [
                'codigo'      => 'rt',
                'nombre'      => 'Recojo en Tienda',
                'descripcion' => 'El cliente recoge en la tienda',
                'icono'       => 'FaStore',
                'color'       => 'amber',
                'orden'       => 1,
                'activo'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'codigo'      => 'de',
                'nombre'      => 'Despacho a Domicilio',
                'descripcion' => 'Chofer entrega en dirección del cliente',
                'icono'       => 'FaTruck',
                'color'       => 'blue',
                'orden'       => 2,
                'activo'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'codigo'      => 'pa',
                'nombre'      => 'Despacho Parcial',
                'descripcion' => 'Entrega dividida en varios despachos',
                'icono'       => 'FaBoxes',
                'color'       => 'purple',
                'orden'       => 3,
                'activo'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ], ['codigo'], ['nombre', 'descripcion', 'icono', 'color', 'orden', 'updated_at']);

        // tipo_despacho
        DB::table('tipo_despacho')->upsert([
            [
                'codigo'      => 'in',
                'nombre'      => 'Inmediato',
                'descripcion' => 'Se entrega ahora',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'codigo'      => 'pr',
                'nombre'      => 'Programado',
                'descripcion' => 'Se entrega en fecha futura',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ], ['codigo'], ['nombre', 'descripcion', 'updated_at']);

        // estado_entrega
        DB::table('estado_entrega')->upsert([
            [
                'codigo'     => 'pe',
                'nombre'     => 'Pendiente',
                'color'      => 'orange',
                'orden'      => 1,
                'es_final'   => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo'     => 'ec',
                'nombre'     => 'En Camino',
                'color'      => 'blue',
                'orden'      => 2,
                'es_final'   => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo'     => 'en',
                'nombre'     => 'Entregado',
                'color'      => 'green',
                'orden'      => 3,
                'es_final'   => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo'     => 'ca',
                'nombre'     => 'Cancelado',
                'color'      => 'red',
                'orden'      => 4,
                'es_final'   => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['codigo'], ['nombre', 'color', 'orden', 'es_final', 'updated_at']);

        // quien_entrega
        DB::table('quien_entrega')->upsert([
            ['codigo' => 'almacen',  'nombre' => 'Almacén',  'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'vendedor', 'nombre' => 'Vendedor', 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'chofer',   'nombre' => 'Chofer',   'created_at' => now(), 'updated_at' => now()],
        ], ['codigo'], ['nombre', 'updated_at']);
    }
}
