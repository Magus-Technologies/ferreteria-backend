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
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            // Campos de reporte
            $table->string('email_reporte')->nullable();
            $table->string('whatsapp_reporte')->nullable();
            $table->boolean('reporte_enviado')->default(false);
            
            // Campos de supervisión
            $table->string('supervisor_id_validador')->nullable();
            $table->boolean('supervisor_validado')->default(false);
            
            // Foreign key para supervisor
            $table->foreign('supervisor_id_validador')
                  ->references('id')
                  ->on('user')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id_validador']);
            $table->dropColumn([
                'email_reporte',
                'whatsapp_reporte',
                'reporte_enviado',
                'supervisor_id_validador',
                'supervisor_validado',
            ]);
        });
    }
};
