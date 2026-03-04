<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla principal de requerimientos internos
        if (!Schema::hasTable('requerimientos_internos')) {
            Schema::create('requerimientos_internos', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique()->comment('Auto-generado: REQ-YYYY-NNN');
                $table->string('titulo', 255);
                $table->string('area', 100)->comment('Almacén, Mantenimiento, Producción, etc.');
                $table->date('fecha_requerida');
                $table->enum('prioridad', ['BAJA', 'MEDIA', 'ALTA', 'URGENTE'])->default('MEDIA');
                $table->enum('tipo_solicitud', ['OC', 'OS'])->comment('OC=Orden Compra, OS=Orden Servicio');
                $table->text('observaciones')->nullable();
                $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'anulado'])->default('pendiente');
                $table->integer('proveedor_sugerido_id')->nullable();
                $table->string('user_id', 191);
                $table->timestamps();

                $table->index('estado', 'idx_ri_estado');
                $table->index('tipo_solicitud', 'idx_ri_tipo');
                $table->index('area', 'idx_ri_area');
                $table->index('user_id', 'idx_ri_user');
                $table->index('fecha_requerida', 'idx_ri_fecha');
            });

            // Foreign keys
            if (Schema::hasTable('proveedor')) {
                Schema::table('requerimientos_internos', function (Blueprint $table) {
                    $table->foreign('proveedor_sugerido_id')->references('id')->on('proveedor')->onDelete('set null')->onUpdate('cascade');
                });
            }

            if (Schema::hasTable('user')) {
                Schema::table('requerimientos_internos', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade')->onUpdate('cascade');
                });
            }
        }

        // 2. Productos de un requerimiento interno (tipo OC)
        if (!Schema::hasTable('requerimiento_interno_productos')) {
            Schema::create('requerimiento_interno_productos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('requerimiento_id');
                $table->integer('producto_id');
                $table->decimal('cantidad', 9, 3);
                $table->string('unidad', 50)->nullable()->comment('UND, KG, MT, etc.');
                $table->timestamp('created_at')->useCurrent()->nullable();

                $table->index('requerimiento_id', 'idx_rip_req');
                $table->index('producto_id', 'idx_rip_prod');

                $table->foreign('requerimiento_id')->references('id')->on('requerimientos_internos')->onDelete('cascade')->onUpdate('cascade');
            });

            if (Schema::hasTable('producto')) {
                Schema::table('requerimiento_interno_productos', function (Blueprint $table) {
                    $table->foreign('producto_id')->references('id')->on('producto')->onDelete('cascade')->onUpdate('cascade');
                });
            }
        }

        // 3. Detalle de servicio de un requerimiento interno (tipo OS)
        if (!Schema::hasTable('requerimiento_interno_servicios')) {
            Schema::create('requerimiento_interno_servicios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('requerimiento_id');
                $table->string('tipo_servicio', 100)->nullable()->comment('Mantenimiento, Instalación, etc.');
                $table->text('descripcion_servicio');
                $table->string('lugar_ejecucion', 255)->nullable();
                $table->date('fecha_inicio_estimada')->nullable();
                $table->decimal('presupuesto_referencial', 12, 2)->nullable();
                $table->integer('duracion_cantidad')->nullable();
                $table->string('duracion_unidad', 20)->nullable()->comment('horas, dias, semanas');
                $table->timestamp('created_at')->useCurrent()->nullable();

                $table->index('requerimiento_id', 'idx_ris_req');

                $table->foreign('requerimiento_id')->references('id')->on('requerimientos_internos')->onDelete('cascade')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('requerimiento_interno_servicios');
        Schema::dropIfExists('requerimiento_interno_productos');
        Schema::dropIfExists('requerimientos_internos');
    }
};
