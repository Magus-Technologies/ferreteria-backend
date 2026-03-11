<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configuración: qué acciones requieren autorización por rol
        Schema::create('autorizaciones_config', function (Blueprint $table) {
            $table->id();
            $table->integer('role_id');
            $table->string('modulo', 100)->comment('productos, ventas, clientes, etc.');
            $table->enum('accion', ['crear', 'editar', 'eliminar']);
            $table->boolean('requiere_autorizacion')->default(true);
            $table->string('autorizador_id', 191)->nullable()->comment('Usuario aprobador; null = cualquier admin');
            $table->string('created_by', 191);
            $table->timestamps();

            $table->unique(['role_id', 'modulo', 'accion'], 'uq_config_rol_mod_acc');
            $table->index('role_id', 'idx_ac_role');

            $table->foreign('role_id')->references('id')->on('role')->onDelete('cascade');
            $table->foreign('autorizador_id')->references('id')->on('user')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('user')->onDelete('cascade');
        });

        // 2. Solicitudes de autorización
        Schema::create('solicitudes_autorizacion', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('solicitante_id', 191);
            $table->string('autorizador_id', 191)->nullable();
            $table->integer('role_id');
            $table->string('modulo', 100);
            $table->enum('accion', ['crear', 'editar', 'eliminar']);
            $table->text('descripcion');
            $table->json('metadata')->nullable();
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->enum('tipo_aprobacion', ['temporal', 'permanente'])->nullable();
            $table->integer('duracion_horas')->nullable()->comment('Si temporal, cuántas horas');
            $table->string('respondido_por', 191)->nullable();
            $table->timestamp('respondido_at')->nullable();
            $table->text('comentario_respuesta')->nullable();
            $table->timestamps();

            $table->index('estado', 'idx_sa_estado');
            $table->index('solicitante_id', 'idx_sa_solicitante');
            $table->index('autorizador_id', 'idx_sa_autorizador');
            $table->index(['modulo', 'accion'], 'idx_sa_mod_acc');

            $table->foreign('solicitante_id')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('autorizador_id')->references('id')->on('user')->onDelete('set null');
            $table->foreign('role_id')->references('id')->on('role')->onDelete('cascade');
            $table->foreign('respondido_por')->references('id')->on('user')->onDelete('set null');
        });

        // 3. Autorizaciones otorgadas (activas)
        Schema::create('autorizaciones_otorgadas', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('user_id', 191);
            $table->integer('role_id');
            $table->string('modulo', 100);
            $table->enum('accion', ['crear', 'editar', 'eliminar']);
            $table->enum('tipo', ['temporal', 'permanente']);
            $table->timestamp('fecha_expiracion')->nullable()->comment('null si permanente');
            $table->string('otorgada_por', 191);
            $table->string('solicitud_id', 191)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'modulo', 'accion'], 'uq_otorgada_user_mod_acc');
            $table->index('user_id', 'idx_ao_user');
            $table->index('activa', 'idx_ao_activa');
            $table->index('fecha_expiracion', 'idx_ao_expiracion');

            $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('role')->onDelete('cascade');
            $table->foreign('otorgada_por')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('solicitud_id')->references('id')->on('solicitudes_autorizacion')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizaciones_otorgadas');
        Schema::dropIfExists('solicitudes_autorizacion');
        Schema::dropIfExists('autorizaciones_config');
    }
};
