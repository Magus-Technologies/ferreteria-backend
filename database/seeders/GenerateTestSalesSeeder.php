<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateTestSalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Crea un par de ventas con 3 productos cada una para probar el Kardex.
     *
     * @return void
     */
    public function run()
    {
        // Obtener datos base
        $user = DB::table('user')->first();
        $cliente = DB::table('cliente')->where('id', '>', 0)->first();
        $almacen = DB::table('almacen')->where('id', '>', 0)->first();
        
        if (!$user) {
            $this->command->error('No hay usuarios registrados.');
            return;
        }
        if (!$cliente) {
            $this->command->error('No hay clientes registrados.');
            return;
        }
        if (!$almacen) {
            $this->command->error('No hay almacenes registrados.');
            return;
        }

        // Crear 2 ventas de prueba
        for ($i = 1; $i <= 2; $i++) {
            $ventaId = (string) Str::ulid();
            $numeroVenta = rand(100, 9999);
            
            // 1. Insertar Venta (Cabecera)
            DB::table('venta')->insert([
                'id' => $ventaId,
                'tipo_documento' => '01', // Factura
                'serie' => 'TEST',
                'numero' => $numeroVenta,
                'descripcion' => 'Venta de prueba para Kardex',
                'forma_de_pago' => 'co', // Contado
                'tipo_moneda' => 's', // Soles
                'tipo_de_cambio' => 1,
                'fecha' => now(),
                'estado_de_venta' => 'cr', // Creado
                'cliente_id' => $cliente->id,
                'user_id' => $user->id,
                'almacen_id' => $almacen->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Obtener 3 productos del almacén
            $productosAlmacen = DB::table('productoalmacen')
                ->where('almacen_id', $almacen->id)
                ->limit(3)
                ->get();

            if ($productosAlmacen->count() === 0) {
                $this->command->error('No hay productos en el almacén ' . $almacen->id);
                continue;
            }

            foreach ($productosAlmacen as $pa) {
                // ProductoAlmacenVenta (Pivote de producto)
                $pavId = DB::table('productoalmacenventa')->insertGetId([
                    'venta_id' => $ventaId,
                    'producto_almacen_id' => $pa->id,
                    'costo' => 10.00,
                    'cantidad' => 2.00,
                    'precio_unitario' => 25.00,
                ]);

                // Buscar una unidad para este producto o la primera disponible
                $unidad = DB::table('unidadderivadainmutable')->first();

                if ($unidad) {
                    // UnidadDerivadaInmutableVenta (Detalle de unidad)
                    DB::table('unidadderivadainmutableventa')->insert([
                        'unidad_derivada_inmutable_id' => $unidad->id,
                        'producto_almacen_venta_id' => $pavId,
                        'factor' => 1,
                        'cantidad' => 2.00,
                        'cantidad_pendiente' => 0,
                        'precio' => 25.00,
                        'recargo' => 0,
                        'descuento' => 0,
                        'comision' => 0,
                    ]);
                }
            }
            
            $this->command->info("Venta TEST-{$numeroVenta} creada con ID: {$ventaId} (3 productos)");
        }
        
        $this->command->info('Proceso de seeder completado.');
    }
}
