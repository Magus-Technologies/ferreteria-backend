<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class JarumyUserPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("🔍 Buscando usuario JARUMY ELENA FERNANDEZ ALVAREZ...");
        
        // Buscar el usuario por email
        $user = User::where('email', 'vcanchari38@gmail.com')->first();
        
        if (!$user) {
            $this->command->error("❌ Usuario no encontrado con email: vcanchari38@gmail.com");
            $this->command->warn("⚠️  Asegúrate de que el usuario exista en la base de datos antes de ejecutar este seeder.");
            return;
        }

        $this->command->info("✓ Usuario encontrado: {$user->name}");
        $this->command->info("  ID: {$user->id}");
        $this->command->info("  Email: {$user->email}");
        
        // Obtener todos los permisos disponibles en el sistema
        $todosLosPermisos = Permission::all();
        
        if ($todosLosPermisos->isEmpty()) {
            $this->command->warn("⚠️  No hay permisos en el sistema. Ejecuta primero los otros seeders de permisos.");
            return;
        }

        $this->command->info("\n📋 Total de permisos disponibles: {$todosLosPermisos->count()}");
        
        // Obtener IDs de todos los permisos
        $permisosIds = $todosLosPermisos->pluck('id')->toArray();
        
        // Asignar todos los permisos al usuario
        $user->permissions()->sync($permisosIds);
        
        $this->command->info("\n✅ Todos los permisos han sido asignados exitosamente a {$user->name}");
        $this->command->info("\n📝 Lista de permisos asignados:");
        
        foreach ($todosLosPermisos as $permiso) {
            $this->command->info("  • {$permiso->name}" . ($permiso->descripcion ? " - {$permiso->descripcion}" : ""));
        }
        
        $this->command->info("\n🎉 Seeder completado exitosamente");
    }
}
