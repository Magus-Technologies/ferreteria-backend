<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Actualiza la tabla motivo_nota con el catálogo completo de SUNAT:
     * - Catálogo 09: Tipos de Nota de Crédito
     * - Catálogo 10: Tipos de Nota de Débito
     */
    public function up(): void
    {
        // Deshabilitar verificación de foreign keys temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Limpiar datos existentes
        DB::table('motivo_nota')->delete();

        // Insertar Catálogo 09 - Tipos de Nota de Crédito
        DB::table('motivo_nota')->insert([
            [
                'tipo' => 'NC',
                'codigo_sunat' => '01',
                'descripcion' => 'Anulación de la operación',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '02',
                'descripcion' => 'Anulación por error en el RUC',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '03',
                'descripcion' => 'Corrección por error en la descripción',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '04',
                'descripcion' => 'Descuento global',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '05',
                'descripcion' => 'Descuento por ítem',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '06',
                'descripcion' => 'Devolución total',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '07',
                'descripcion' => 'Devolución por ítem',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '08',
                'descripcion' => 'Bonificación',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '09',
                'descripcion' => 'Disminución en el valor',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '10',
                'descripcion' => 'Otros conceptos',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '11',
                'descripcion' => 'Ajustes de exportación',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '12',
                'descripcion' => 'Ajustes afectos al IVAP',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'NC',
                'codigo_sunat' => '13',
                'descripcion' => 'Corrección del monto neto / cuotas',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);

        // Insertar Catálogo 10 - Tipos de Nota de Débito
        DB::table('motivo_nota')->insert([
            [
                'tipo' => 'ND',
                'codigo_sunat' => '01',
                'descripcion' => 'Intereses por mora',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'ND',
                'codigo_sunat' => '02',
                'descripcion' => 'Aumento en el valor',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'ND',
                'codigo_sunat' => '03',
                'descripcion' => 'Penalidades / otros conceptos',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'ND',
                'codigo_sunat' => '10',
                'descripcion' => 'Ajustes de exportación',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'tipo' => 'ND',
                'codigo_sunat' => '11',
                'descripcion' => 'Ajustes afectos al IVAP',
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
        
        // Reactivar verificación de foreign keys
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No es necesario revertir, ya que solo actualiza datos
        // Si se requiere, se puede restaurar desde un backup
    }
};
