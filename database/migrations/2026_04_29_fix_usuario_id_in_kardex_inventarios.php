<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            // Primero eliminar la foreign key si existe
            try {
                $table->dropForeign('kardex_inventarios_usuario_id_foreign');
            } catch (\Exception $e) {
                // Si no existe, continuar
            }
        });
        
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            // Cambiar usuario_id de unsignedBigInteger a string para soportar ULIDs
            $table->string('usuario_id', 26)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id')->nullable()->change();
        });
    }
};
