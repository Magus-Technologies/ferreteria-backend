<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_notificaciones', function (Blueprint $table) {
            $table->string('id', 191)->primary();

            $table->string('user_id', 191); // Configuración por usuario

            // Tipo de notificación: cumpleanos, entrega, pago, vale, etc.
            $table->string('tipo', 50);

            // Si está habilitada la notificación
            $table->boolean('habilitado')->default(true);

            // Para cumpleaños: cuántos días antes avisar (ej: 7 = avisa 7 días antes)
            $table->unsignedTinyInteger('dias_anticipacion')->default(7);

            // JSON para configuraciones extra futuras
            $table->json('extra')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();

            // Un usuario solo puede tener una configuración por tipo
            $table->unique(['user_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_notificaciones');
    }
};
