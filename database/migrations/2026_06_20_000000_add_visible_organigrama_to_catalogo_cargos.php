<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_cargos', function (Blueprint $table) {
            // Permite ocultar un cargo del organigrama sin eliminarlo ni
            // desactivarlo (sigue siendo asignable a usuarios).
            $table->boolean('visible_organigrama')->default(true)->after('staff');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_cargos', function (Blueprint $table) {
            $table->dropColumn('visible_organigrama');
        });
    }
};
