<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar columna cdr_path si no existe
        if (!Schema::hasColumn('comprobantes_electronicos', 'cdr_path')) {
            Schema::table('comprobantes_electronicos', function (Blueprint $table) {
                $table->string('cdr_path', 500)->nullable()->after('hash_cdr')->comment('Ruta del archivo CDR en storage');
            });
        }

        // Actualizar las rutas de CDR existentes de .xml a .zip
        DB::statement("
            UPDATE comprobantes_electronicos 
            SET cdr_path = REPLACE(cdr_path, '.xml', '.zip') 
            WHERE cdr_path LIKE '%.xml'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir las rutas de .zip a .xml
        DB::statement("
            UPDATE comprobantes_electronicos 
            SET cdr_path = REPLACE(cdr_path, '.zip', '.xml') 
            WHERE cdr_path LIKE '%.zip'
        ");
    }
};
