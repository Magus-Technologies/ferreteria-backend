<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('intentos_envio_sunat')) {
            return;
        }
        
        Schema::create('intentos_envio_sunat', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement()->primary();
            $table->string('comprobante_id', 191);
            $table->unsignedInteger('numero_intento');
            $table->dateTime('fecha_intento')->useCurrent();
            $table->enum('resultado', ['exitoso', 'fallido', 'pendiente']);
            $table->string('codigo_respuesta', 50)->nullable();
            $table->text('mensaje_respuesta')->nullable();
            $table->string('ticket_numero', 50)->nullable();

            $table->index('comprobante_id', 'idx_comprobante_id');
            $table->index('fecha_intento', 'idx_fecha_intento');
        });

        // Agregar foreign key solo si la tabla existe
        if (Schema::hasTable('comprobantes_electronicos')) {
            Schema::table('intentos_envio_sunat', function (Blueprint $table) {
                $table->foreign('comprobante_id')->references('id')->on('comprobantes_electronicos')->onDelete('cascade')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_envio_sunat');
    }
};
