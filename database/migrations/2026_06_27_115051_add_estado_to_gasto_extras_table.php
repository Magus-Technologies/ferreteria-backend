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
        Schema::table('gastos_extras', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'aprobado', 'anulado'])->default('aprobado')->after('concepto');
        });
    }

    public function down(): void
    {
        Schema::table('gastos_extras', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
