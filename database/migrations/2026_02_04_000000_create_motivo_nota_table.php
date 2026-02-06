<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('motivo_nota')) {
            return;
        }
        
        Schema::create('motivo_nota', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['NC', 'ND'])->index();
            $table->string('codigo_sunat', 20)->nullable()->comment('Código según catálogo SUNAT');
            $table->string('descripcion', 255);
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();
            $table->unique(['tipo', 'codigo_sunat'], 'uq_tipo_codigo_sunat');
            $table->index(['tipo', 'descripcion'], 'idx_tipo_descripcion');
        });

        // Insertar motivos de Nota de Crédito (Catálogo 09 SUNAT)
        DB::table('motivo_nota')->insert([
            ['tipo' => 'NC', 'codigo_sunat' => '01', 'descripcion' => 'Anulación de la operación', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '02', 'descripcion' => 'Anulación por error en el RUC', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '03', 'descripcion' => 'Corrección por error en la descripción', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '04', 'descripcion' => 'Descuento global', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '05', 'descripcion' => 'Descuento por ítem', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '06', 'descripcion' => 'Devolución total', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '07', 'descripcion' => 'Devolución por ítem', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '08', 'descripcion' => 'Bonificación', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '09', 'descripcion' => 'Disminución en el valor', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '10', 'descripcion' => 'Otros conceptos', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '11', 'descripcion' => 'Ajustes de afectación del IVA', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '12', 'descripcion' => 'Ajustes de afectación del IGV', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '13', 'descripcion' => 'Ajustes en montos y/o fechas de pago (Cuotas)', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '14', 'descripcion' => 'Resumen diario de boletas', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'NC', 'codigo_sunat' => '15', 'descripcion' => 'Anulación de boleta de venta - Otros', 'estado' => 1, 'created_at' => now()],
            
            // Insertar motivos de Nota de Débito (Catálogo 10 SUNAT)
            ['tipo' => 'ND', 'codigo_sunat' => '01', 'descripcion' => 'Intereses por mora', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'ND', 'codigo_sunat' => '02', 'descripcion' => 'Aumento en el valor', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'ND', 'codigo_sunat' => '03', 'descripcion' => 'Penalidades / otros conceptos', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'ND', 'codigo_sunat' => '10', 'descripcion' => 'Ajustes de afectación del IVA', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'ND', 'codigo_sunat' => '11', 'descripcion' => 'Ajustes de afectación del IGV', 'estado' => 1, 'created_at' => now()],
            ['tipo' => 'ND', 'codigo_sunat' => '12', 'descripcion' => 'Ajustes en montos y/o fechas de pago', 'estado' => 1, 'created_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('motivo_nota');
    }
};
