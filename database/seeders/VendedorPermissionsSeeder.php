<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VendedorPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos necesarios para vendedores
        $permisos = [
            'facturacion-electronica.index',
            'caja.listado',
            'caja.create',
            'caja.update',
            'caja.aperturar',
            'caja.cerrar',
            'caja.consultar',
        ];

        echo " Creando permisos...\n";
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
            echo "  {$permiso}\n";
        }

        // Crear o actualizar rol de vendedor
        echo "\n Configurando rol 'vendedor'...\n";
        $vendedor = Role::firstOrCreate(['name' => 'vendedor']);
        
        // Asignar permisos al rol
        $vendedor->syncPermissions($permisos);
        echo "   Permisos asignados al rol 'vendedor'\n";

        // Asignar rol al usuario específico
        echo "\n Buscando usuario vcanchari@gmail.com...\n";
        $user = User::where('email', 'vcanchari@gmail.com')->first();
        
        if ($user) {
            $user->assignRole('vendedor');
            echo "  Rol 'vendedor' asignado a: {$user->name}\n";
            echo "  Email: {$user->email}\n";
            
            // Mostrar permisos asignados
            echo "\n Permisos del usuario:\n";
            foreach ($user->getAllPermissions() as $permission) {
                echo "  • {$permission->name}\n";
            }
        } else {
            echo "   Usuario no encontrado: vcanchari@gmail.com\n";
            echo "   Verifica que el email sea correcto\n";
        }

        echo "\n Seeder completado exitosamente\n";
    }
}
