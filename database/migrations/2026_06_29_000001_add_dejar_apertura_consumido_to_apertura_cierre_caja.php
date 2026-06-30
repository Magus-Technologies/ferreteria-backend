<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            if (!Schema::hasColumn('apertura_cierre_caja', 'dejar_apertura_consumido')) {
                $table->boolean('dejar_apertura_consumido')
                    ->default(false)
                    ->after('dejar_apertura_asignado_a')
                    ->comment('true cuando el usuario asignado ya usó este efectivo en una apertura');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            $table->dropColumn('dejar_apertura_consumido');
        });
    }
};
