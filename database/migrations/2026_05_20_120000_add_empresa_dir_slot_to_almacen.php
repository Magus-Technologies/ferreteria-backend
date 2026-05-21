<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('almacen', function (Blueprint $table) {
            // Slot de dirección de empresa que corresponde a este almacén (D1/D2/D3/D4).
            // Se guarda el tipo (string), NO el ID de direccion_empresa,
            // porque el controller de empresa hace delete+recreate de las
            // direcciones secundarias en cada save y los IDs cambiarían.
            $table->string('empresa_dir_slot', 2)->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('almacen', function (Blueprint $table) {
            $table->dropColumn('empresa_dir_slot');
        });
    }
};
