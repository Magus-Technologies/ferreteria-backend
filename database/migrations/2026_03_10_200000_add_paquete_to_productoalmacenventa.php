<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productoalmacenventa', function (Blueprint $table) {
            $table->unsignedInteger('paquete_id')->nullable()->after('producto_almacen_id');
            $table->string('paquete_nombre', 255)->nullable()->after('paquete_id');
        });
    }

    public function down(): void
    {
        Schema::table('productoalmacenventa', function (Blueprint $table) {
            $table->dropColumn(['paquete_id', 'paquete_nombre']);
        });
    }
};
