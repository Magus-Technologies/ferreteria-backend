<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transacciones_caja', function (Blueprint $table) {
            // Solo agregar updated_at si no existe
            if (!Schema::hasColumn('transacciones_caja', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transacciones_caja', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
