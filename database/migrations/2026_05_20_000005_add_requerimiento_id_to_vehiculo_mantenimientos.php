<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculo_mantenimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('requerimiento_id')->nullable()->after('vehiculo_id')->index();
            $table->foreign('requerimiento_id')->references('id')->on('requerimientos_internos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculo_mantenimientos', function (Blueprint $table) {
            $table->dropForeign(['requerimiento_id']);
            $table->dropColumn('requerimiento_id');
        });
    }
};
