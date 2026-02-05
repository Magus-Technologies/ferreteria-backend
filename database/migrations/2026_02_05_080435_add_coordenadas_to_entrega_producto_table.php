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
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable()->after('direccion_entrega');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud']);
        });
    }
};
