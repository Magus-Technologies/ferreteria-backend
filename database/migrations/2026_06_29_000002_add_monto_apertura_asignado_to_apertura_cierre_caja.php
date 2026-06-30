<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            if (!Schema::hasColumn('apertura_cierre_caja', 'monto_apertura_asignado')) {
                $table->decimal('monto_apertura_asignado', 10, 2)
                    ->default(0)
                    ->after('monto_apertura')
                    ->comment('Parte del monto de apertura que proviene de efectivo asignado de otro cierre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            $table->dropColumn('monto_apertura_asignado');
        });
    }
};
