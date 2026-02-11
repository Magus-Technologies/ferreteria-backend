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
        Schema::table('desplieguedepago', function (Blueprint $table) {
            // Eliminar el constraint único del campo 'name'
            // Esto permite que múltiples bancos tengan métodos con el mismo nombre
            // Ejemplo: BCP puede tener "Izipay" y BBVA también puede tener "Izipay"
            $table->dropUnique('DespliegueDePago_name_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('desplieguedepago', function (Blueprint $table) {
            // Restaurar el constraint único si se hace rollback
            $table->unique('name', 'DespliegueDePago_name_key');
        });
    }
};
