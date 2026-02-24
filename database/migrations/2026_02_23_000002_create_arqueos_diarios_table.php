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
        Schema::create('arqueos_diarios', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('apertura_cierre_caja_id');
            $table->string('user_id');
            $table->date('fecha_arqueo')->comment('Día del arqueo');

            // Montos
            $table->decimal('monto_cierre', 12, 2)->nullable();
            $table->decimal('monto_cierre_efectivo', 12, 2)->nullable();
            $table->decimal('monto_cierre_cuentas', 12, 2)->nullable();

            // Conteo
            $table->json('conteo_billetes_monedas')->nullable();

            // Diferencias
            $table->decimal('diferencia_efectivo', 12, 2)->nullable();
            $table->decimal('diferencia_total', 12, 2)->nullable();

            // Comentarios
            $table->text('comentarios')->nullable();

            // Supervisor
            $table->string('supervisor_id_validador')->nullable();
            $table->boolean('supervisor_validado')->default(false);

            // Estado del cierre
            $table->enum('estado_cierre', ['pendiente', 'en_proceso', 'aprobado', 'no_realizado'])->default('pendiente');

            // Reporte
            $table->string('email_reporte')->nullable();
            $table->string('whatsapp_reporte')->nullable();

            // Snapshot del resumen calculado (para no tener que recalcular)
            $table->json('resumen_snapshot')->nullable()->comment('Snapshot completo del resumen calculado al momento del arqueo');

            $table->timestamps();

            // Foreign keys
            $table->foreign('apertura_cierre_caja_id')
                ->references('id')
                ->on('apertura_cierre_caja')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // Indexes
            $table->index('apertura_cierre_caja_id');
            $table->index('user_id');
            $table->index('fecha_arqueo');
            $table->index(['apertura_cierre_caja_id', 'fecha_arqueo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arqueos_diarios');
    }
};
