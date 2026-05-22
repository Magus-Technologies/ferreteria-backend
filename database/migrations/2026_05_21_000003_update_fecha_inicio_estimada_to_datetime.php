<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requerimiento_interno_servicios', function (Blueprint $table) {
            $table->dateTime('fecha_inicio_estimada')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('requerimiento_interno_servicios', function (Blueprint $table) {
            $table->date('fecha_inicio_estimada')->nullable()->change();
        });
    }
};
