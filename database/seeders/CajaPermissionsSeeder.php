<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CajaPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Permisos para Cajas Principales
        $permisosCajaPrincipal = [
            [
                'name' => 'caja-principal.create',
                'descripcion' => 'Crear cajas principales',
            ],
            [
                'name' => 'caja-principal.listado',
                'descripcion' => 'Listar cajas principales',
            ],
            [
                'name' => 'caja-principal.update',
                'descripcion' => 'Actualizar cajas principales',
            ],
            [
                'name' => 'caja-principal.delete',
                'descripcion' => 'Eliminar cajas principales',
            ],
        ];

        // Permisos para Sub-Cajas
        $permisosSubCaja = [
            [
                'name' => 'sub-caja.create',
                'descripcion' => 'Crear sub-cajas',
            ],
            [
                'name' => 'sub-caja.listado',
                'descripcion' => 'Listar sub-cajas',
            ],
            [
                'name' => 'sub-caja.update',
                'descripcion' => 'Actualizar sub-cajas',
            ],
            [
                'name' => 'sub-caja.delete',
                'descripcion' => 'Eliminar sub-cajas',
            ],
        ];

        // Permisos para Transacciones
        $permisosTransacciones = [
            [
                'name' => 'transaccion-caja.create',
                'descripcion' => 'Registrar transacciones en cajas',
            ],
            [
                'name' => 'transaccion-caja.listado',
                'descripcion' => 'Listar transacciones de cajas',
            ],
        ];

        // Combinar todos los permisos
        $todosLosPermisos = array_merge(
            $permisosCajaPrincipal,
            $permisosSubCaja,
            $permisosTransacciones
        );

        // Crear permisos
        $permisosCreados = [];
        foreach ($todosLosPermisos as $permiso) {
            $permisoCreado = Permission::firstOrCreate(
                ['name' => $permiso['name']],
                ['descripcion' => $permiso['descripcion']]
            );
            $permisosCreados[] = $permisoCreado->id;
            
            $this->command->info("✓ Permiso creado/verificado: {$permiso['name']}");
        }

        // Asignar permisos al usuario ADMIN
        $admin = User::where('email', 'admin@aplication.com')->first();
        
        if ($admin) {
            // Obtener permisos actuales del admin
            $permisosActuales = $admin->permissions()->pluck('permission.id')->toArray();
            
            // Combinar con los nuevos
            $todosLosPermisosIds = array_unique(array_merge($permisosActuales, $permisosCreados));
            
            // Sincronizar permisos
            $admin->permissions()->sync($todosLosPermisosIds);
            
            $this->command->info("✓ Permisos asignados al usuario ADMIN");
            $this->command->info("  Total de permisos: " . count($todosLosPermisosIds));
        } else {
            $this->command->warn("⚠ Usuario ADMIN no encontrado");
        }

        $this->command->info("\n✅ Seeder de permisos de cajas completado exitosamente");
    }
}
