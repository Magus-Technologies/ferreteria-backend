<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deuda_personals', function (Blueprint $table) {
            $table->decimal('monto_original', 10, 2)->after('monto')->nullable();
            $table->decimal('monto_abonado', 10, 2)->default(0)->after('monto_original');
            $table->decimal('saldo_pendiente', 10, 2)->after('monto_abonado')->nullable();
        });

        // Migrar datos existentes
        DB::statement("
            UPDATE deuda_personals 
            SET monto_original = monto,
                saldo_pendiente = CASE 
                    WHEN estado = 'pagado' THEN 0 
                    ELSE monto 
                END,
                monto_abonado = CASE 
                    WHEN estado = 'pagado' THEN monto 
                    ELSE 0 
                END
            WHERE monto_original IS NULL
        ");

        // Hacer los campos NOT NULL después de migrar datos
        Schema::table('deuda_personals', function (Blueprint $table) {
            $table->decimal('monto_original', 10, 2)->nullable(false)->change();
            $table->decimal('saldo_pendiente', 10, 2)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deuda_personals', function (Blueprint $table) {
            $table->dropColumn(['monto_original', 'monto_abonado', 'saldo_pendiente']);
        });
    }
};
