<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-runnable seeder: sets rol_sistema on the role table by name.
 * Run this after recreating roles from scratch (except admin_global which is preserved).
 *
 * Usage: php artisan db:seed --class=RolSistemaSeeder
 */
class RolSistemaSeeder extends Seeder
{
    private array $mapping = [
        'admin_global' => 'ADMINISTRADOR',
        'vendedor'     => 'VENDEDOR',
        'almacenero'   => 'ALMACENERO',
        'contador'     => 'CONTADOR',
        'despachador'  => 'DESPACHADOR',
    ];

    public function run(): void
    {
        foreach ($this->mapping as $name => $rolSistema) {
            $updated = DB::table('role')
                ->where('name', $name)
                ->update(['rol_sistema' => $rolSistema]);

            $this->command->line(
                $updated
                    ? "  <info>✓</info> {$name} → {$rolSistema}"
                    : "  <comment>-</comment> {$name} no encontrado (se creará después)"
            );
        }

        $this->command->info('RolSistema: mapping aplicado.');
    }
}
