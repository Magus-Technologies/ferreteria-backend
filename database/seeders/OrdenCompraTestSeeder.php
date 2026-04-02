<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraProducto;
use App\Models\User;
use App\Models\Proveedor;
use App\Models\Producto;
use Carbon\Carbon;

class OrdenCompraTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener datos necesarios
        $user = User::first();
        if (!$user) {
            $this->command->error('No hay usuarios en la base de datos');
            return;
        }

        $proveedores = Proveedor::limit(3)->get();
        if ($proveedores->isEmpty()) {
            $this->command->error('No hay proveedores en la base de datos');
            return;
        }

        $productos = Producto::limit(10)->get();
        if ($productos->isEmpty()) {
            $this->command->error('No hay productos en la base de datos');
            return;
        }

        $almacenId = 1;

        // Crear órdenes de compra con diferentes fechas
        $fechas = [
            Carbon::now()->subDays(30), // Hace 30 días
            Carbon::now()->subDays(20), // Hace 20 días
            Carbon::now()->subDays(15), // Hace 15 días
            Carbon::now()->subDays(10), // Hace 10 días
            Carbon::now()->subDays(5),  // Hace 5 días
            Carbon::now()->subDays(2),  // Hace 2 días
            Carbon::now()->subDay(),    // Ayer
            Carbon::now(),              // Hoy
        ];

        $estados = ['pendiente', 'en_proceso', 'completada', 'anulada'];
        $tiposMoneda = ['s', 'd'];
        $formasPago = ['co', 'cr'];

        foreach ($fechas as $index => $fecha) {
            $proveedor = $proveedores->random();
            $estado = $estados[array_rand($estados)];
            $tipoMoneda = $tiposMoneda[array_rand($tiposMoneda)];
            $formaPago = $formasPago[array_rand($formasPago)];

            // Generar código único
            $year = $fecha->format('Y');
            $lastOrden = OrdenCompra::where('codigo', 'like', "OC-{$year}-%")->orderBy('id', 'desc')->first();
            $nextNumber = $lastOrden ? intval(substr($lastOrden->codigo, -3)) + 1 : $index + 1;
            $codigo = sprintf('OC-%s-%03d', $year, $nextNumber);

            // Crear orden de compra
            $orden = OrdenCompra::create([
                'codigo' => $codigo,
                'requerimiento_id' => null,
                'proveedor_id' => $proveedor->id,
                'fecha' => $fecha->format('Y-m-d'),
                'tipo_moneda' => $tipoMoneda,
                'tipo_de_cambio' => $tipoMoneda === 's' ? 1.0000 : 3.7500,
                'ruc' => $proveedor->ruc,
                'tipo_documento' => null,
                'serie' => null,
                'numero' => null,
                'guia' => null,
                'percepcion' => 0,
                'forma_de_pago' => $formaPago,
                'numero_dias' => $formaPago === 'cr' ? 30 : null,
                'fecha_vencimiento' => $formaPago === 'cr' ? $fecha->copy()->addDays(30)->format('Y-m-d') : null,
                'estado' => $estado,
                'user_id' => $user->id,
                'almacen_id' => $almacenId,
            ]);

            // Agregar productos aleatorios (entre 2 y 5 productos)
            $cantidadProductos = rand(2, 5);
            $productosSeleccionados = $productos->random($cantidadProductos);

            foreach ($productosSeleccionados as $producto) {
                $cantidad = rand(5, 50);
                $precio = rand(10, 500);
                $subtotal = $cantidad * $precio;
                $flete = rand(0, 50);

                OrdenCompraProducto::create([
                    'orden_compra_id' => $orden->id,
                    'producto_id' => $producto->id,
                    'codigo' => $producto->cod_producto,
                    'nombre' => $producto->name,
                    'marca' => $producto->marca->name ?? 'SIN MARCA',
                    'unidad' => $producto->unidad_medida->name ?? 'UND',
                    'cantidad' => $cantidad,
                    'cantidad_pendiente' => $cantidad,
                    'precio' => $precio,
                    'subtotal' => $subtotal,
                    'flete' => $flete,
                    'vencimiento' => null,
                    'lote' => null,
                ]);
            }

            $this->command->info("✓ Orden {$codigo} creada - Fecha: {$fecha->format('Y-m-d')} - Estado: {$estado}");
        }

        $this->command->info("\n✓ Se crearon " . count($fechas) . " órdenes de compra de prueba");
    }
}
