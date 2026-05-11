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
        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            $table->decimal('monto', 15, 4)->change();
            $table->decimal('sobrecargo_aplicado', 15, 4)->nullable()->change();
            $table->decimal('recibe_efectivo', 15, 4)->nullable()->change();
        });

        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->decimal('monto', 15, 4)->change();
        });

        Schema::table('cobroventa', function (Blueprint $table) {
            $table->decimal('monto', 15, 4)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            $table->decimal('monto', 8, 4)->change();
            $table->decimal('sobrecargo_aplicado', 8, 4)->nullable()->change();
            $table->decimal('recibe_efectivo', 8, 4)->nullable()->change();
        });

        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->decimal('monto', 8, 2)->change();
        });

        Schema::table('cobroventa', function (Blueprint $table) {
            $table->decimal('monto', 8, 2)->change();
        });
    }
};
