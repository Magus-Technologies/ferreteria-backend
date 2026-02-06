<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nota_debito')) {
            return;
        }
        
        Schema::create('nota_debito', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->enum('tipo_documento', ['nd'])->default('nd');
            $table->string('serie', 191);
            $table->unsignedInteger('numero');
            $table->string('venta_id', 191)->comment('Venta que se incrementa');
            $table->string('comprobante_id_referencia', 191)->nullable()->comment('ID del comprobante original que afecta');
            $table->unsignedBigInteger('motivo_id');
            $table->text('descripcion')->nullable();
            $table->decimal('monto_total', 12, 2)->default(0)->comment('Total de la nota de débito');
            $table->decimal('monto_igv', 12, 2)->default(0)->comment('IGV de la nota');
            $table->decimal('monto_subtotal', 12, 2)->default(0)->comment('Subtotal antes de IGV');
            $table->string('referencia_documento', 50)->nullable()->comment('Serie-Número del doc. que afecta');
            $table->dateTime('fecha');
            $table->enum('estado', ['borrador', 'pendiente', 'enviado', 'aceptado', 'rechazado', 'cancelado'])->default('borrador');
            $table->string('usuario_id', 191);
            $table->integer('almacen_id');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['serie', 'numero'], 'uq_serie_numero_nd');
            $table->index('venta_id', 'idx_venta_id_nd');
            $table->index('motivo_id', 'idx_motivo_id_nd');
            $table->index('estado', 'idx_estado_nd');
            $table->index('usuario_id', 'idx_usuario_id_nd');
            $table->index('almacen_id', 'idx_almacen_id_nd');
            $table->index('fecha', 'idx_fecha_nd');
        });

        // Agregar foreign keys solo si las tablas existen
        if (Schema::hasTable('venta')) {
            Schema::table('nota_debito', function (Blueprint $table) {
                $table->foreign('venta_id')->references('id')->on('venta')->onDelete('cascade')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('motivo_nota')) {
            Schema::table('nota_debito', function (Blueprint $table) {
                $table->foreign('motivo_id')->references('id')->on('motivo_nota')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('user')) {
            Schema::table('nota_debito', function (Blueprint $table) {
                $table->foreign('usuario_id')->references('id')->on('user')->onDelete('cascade')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('almacen')) {
            Schema::table('nota_debito', function (Blueprint $table) {
                $table->foreign('almacen_id')->references('id')->on('almacen')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('comprobantes_electronicos')) {
            Schema::table('nota_debito', function (Blueprint $table) {
                $table->foreign('comprobante_id_referencia')->references('id')->on('comprobantes_electronicos')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_debito');
    }
};
