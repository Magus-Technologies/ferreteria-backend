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
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            // Cambiar xml_firmado y cdr_xml de TEXT a LONGBLOB para almacenar datos binarios
            $table->longText('xml_firmado')->nullable()->change();
            $table->binary('cdr_xml')->nullable()->change();
        });
        
        // Usar query directo para asegurar que sea LONGBLOB
        DB::statement('ALTER TABLE comprobantes_electronicos MODIFY COLUMN cdr_xml LONGBLOB NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->text('xml_firmado')->nullable()->change();
            $table->text('cdr_xml')->nullable()->change();
        });
    }
};
