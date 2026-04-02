<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RequerimientoInterno;
use App\Models\RequerimientoInternoProducto;
use App\Models\User;
use App\Models\Proveedor;
use App\Models\Producto;
use Carbon\Carbon;

class RequerimientoInternoTestSeeder extends Seeder
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
        $productos = Producto::limit(15)->get();
        if ($productos->isEmpty()) {
            $this->command->error('No hay productos en la base de datos');
            return;
        }

        // Áreas de ejemplo
        $areas = ['ALMACEN', 'VENTAS', 'ADMINISTRACION', 'LOGISTICA', 'PRODUCCION'];
        $prioridades = ['BAJA', 'MEDIA', 'ALTA', 'URGENTE'];
        $estados = ['pendiente', 'aprobado', 'rechazado', 'anulado'];
        $tiposSolicitud = ['OC', 'OS'];

        // Crear requerimientos con diferentes fechas
        $fechas = [
            Carbon::now()->subDays(25), // Hace 25 días
            Carbon::now()->subDays(18), // Hace 18 días
            Carbon::now()->subDays(12), // Hace 12 días
            Carbon::now()->subDays(8),  // Hace 8 días
            Carbon::now()->subDays(4),  // Hace 4 días
            Carbon::now()->subDays(2),  // Hace 2 días
            Carbon::now()->subDay(),    // Ayer
            Carbon::now(),              // Hoy
            Carbon::now()->addDay(),    // Mañana
            Carbon::now()->addDays(3),  // En 3 días
        ];

        foreach ($fechas as $index => $fecha) {
            $area = $areas[array_rand($areas)];
            $prioridad = $prioridades[array_rand($prioridades)];
            $estado = $estados[array_rand($estados)];
            $tipoSolicitud = $tiposSolicitud[array_rand($tiposSolicitud)];
            $proveedor = $proveedores->isNotEmpty() ? $proveedores->random() : null;

            // Generar código único
            $year = $fecha->format('Y');
            $lastReq = RequerimientoInterno::where('codigo', 'like', "REQ-{$year}-%")->orderBy('id', 'desc')->first();
            $nextNumber = $lastReq ? intval(substr($lastReq->codigo, -3)) + 1 : $index + 100;
            $codigo = sprintf('REQ-%s-%03d', $year, $nextNumber);

            // Crear requerimiento interno
            $requerimiento = RequerimientoInterno::create([
                'codigo' => $codigo,
                'titulo' => "Solicitud de materiales - {$area}",
                'area' => $area,
                'fecha_requerida' => $fecha->format('Y-m-d'),
                'prioridad' => $prioridad,
                'tipo_solicitud' => $tipoSolicitud,
                'observaciones' => "Requerimiento de productos para el área de {$area}",
                'estado' => $estado,
                'user_id' => $user->id,
                'proveedor_sugerido_id' => $proveedor?->id,
            ]);

            // Agregar productos aleatorios (entre 2 y 6 productos)
            $cantidadProductos = rand(2, 6);
            $productosSeleccionados = $productos->random(min($cantidadProductos, $productos->count()));

            foreach ($productosSeleccionados as $producto) {
                $cantidad = rand(10, 100);
                // Para estados pendiente y aprobado, dejar cantidad pendiente
                $cantidadPendiente = in_array($estado, ['pendiente', 'aprobado']) ? $cantidad : 0;

                RequerimientoInternoProducto::create([
                    'requerimiento_id' => $requerimiento->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'cantidad_pendiente' => $cantidadPendiente,
                    'unidad' => $producto->unidad_medida->name ?? 'UND',
                ]);
            }

            $this->command->info("✓ Requerimiento {$codigo} creado - Fecha: {$fecha->format('Y-m-d')} - Estado: {$estado} - Prioridad: {$prioridad}");
        }

        $this->command->info("\n✓ Se crearon " . count($fechas) . " requerimientos internos de prueba");
    }
}
