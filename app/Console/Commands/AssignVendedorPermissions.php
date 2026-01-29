<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AssignVendedorPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:assign-vendedor {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asignar permisos de vendedor a un usuario por email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        
        $this->info("🔍 Buscando usuario: {$email}");
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("❌ Usuario no encontrado: {$email}");
            $this->warn("💡 Verifica que el email sea correcto");
            return 1;
        }

        $this->info("✅ Usuario encontrado: {$user->name}");

        // Crear permisos necesarios
        $permisos = [
            'facturacion-electronica.index',
            'caja.listado',
            'caja.create',
            'caja.update',
            'caja.aperturar',
            'caja.cerrar',
            'caja.consultar',
        ];

        $this->info("\n📝 Creando permisos...");
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // Crear rol de vendedor si no existe
        $this->info("\n👤 Configurando rol 'vendedor'...");
        $vendedor = Role::firstOrCreate(['name' => 'vendedor']);
        $vendedor->syncPermissions($permisos);

        // Asignar rol al usuario
        $user->assignRole('vendedor');

        $this->info("\n✅ Permisos asignados exitosamente a: {$user->name}");
        
        // Mostrar tabla de permisos
        $this->info("\n📋 Permisos asignados:");
        $this->table(
            ['Permiso'],
            collect($permisos)->map(fn($p) => [$p])->toArray()
        );

        return 0;
    }
}
