<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RequerimientoInterno;
use App\Models\RequerimientoInternoProducto;
use App\Models\User;
use App\Models\Proveedor;
use App\Models\Producto;
use Carbon\Carbon;

class RequerimientoHoySeeder extends Seeder
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
        $estadosValidos = ['pendiente', 'aprobado']; // Solo estados que permiten crear OC
        $tiposSolicitud = ['OC', 'OS'];

        // Crear 5 requerimientos de HOY con estados válidos
        $hoy = Carbon::now();

        for ($i = 0; $i < 5; $i++) {
            $area = $areas[array_rand($areas)];
            $prioridad = $prioridades[array_rand($prioridades)];
            $estado = $estadosValidos[array_rand($estadosValidos)];
            $tipoSolicitud = $tiposSolicitud[array_rand($tiposSolicitud)];
            $proveedor = $proveedores->isNotEmpty() ? $proveedores->random() : null;

            // Generar código único
            $year = $hoy->format('Y');
            $lastReq = RequerimientoInterno::where('codigo', 'like', "REQ-{$year}-%")->orderBy('id', 'desc')->first();
            $nextNumber = $lastReq ? intval(substr($lastReq->codigo, -3)) + 1 : 200 + $i;
            $codigo = sprintf('REQ-%s-%03d', $year, $nextNumber);

            // Crear requerimiento interno
            $requerimiento = RequerimientoInterno::create([
                'codigo' => $codigo,
                'titulo' => "Solicitud urgente - {$area} - " . $hoy->format('d/m/Y'),
                'area' => $area,
                'fecha_requerida' => $hoy->format('Y-m-d'),
                'prioridad' => $prioridad,
                'tipo_solicitud' => $tipoSolicitud,
                'observaciones' => "Requerimiento de hoy para el área de {$area}",
                'estado' => $estado,
                'user_id' => $user->id,
                'proveedor_sugerido_id' => $proveedor?->id,
            ]);

            // Agregar productos aleatorios (entre 3 y 7 productos)
            $cantidadProductos = rand(3, 7);
            $productosSeleccionados = $productos->random(min($cantidadProductos, $productos->count()));

            foreach ($productosSeleccionados as $producto) {
                $cantidad = rand(10, 100);
                // Todos con cantidad pendiente completa
                $cantidadPendiente = $cantidad;

                RequerimientoInternoProducto::create([
                    'requerimiento_id' => $requerimiento->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'cantidad_pendiente' => $cantidadPendiente,
                    'unidad' => $producto->unidad_medida->name ?? 'UND',
                ]);
            }

            $this->command->info("✓ Requerimiento {$codigo} creado - HOY ({$hoy->format('Y-m-d')}) - Estado: {$estado} - Prioridad: {$prioridad} - {$cantidadProductos} productos");
        }

        $this->command->info("\n✓ Se crearon 5 requerimientos de HOY con estados válidos (pendiente/aprobado)");
        $this->command->info("✓ Estos requerimientos deberían aparecer en el sidebar de crear orden de compra");
    }
}
