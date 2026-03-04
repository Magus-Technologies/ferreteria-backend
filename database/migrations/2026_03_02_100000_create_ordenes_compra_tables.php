<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla principal de órdenes de compra
        if (!Schema::hasTable('ordenes_compra')) {
            Schema::create('ordenes_compra', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique()->comment('Auto-generado: OC-YYYY-NNN');
                $table->unsignedBigInteger('requerimiento_id')->nullable();
                $table->integer('proveedor_id')->nullable();
                $table->date('fecha');
                $table->enum('tipo_moneda', ['s', 'd'])->default('s')->comment('s=Soles, d=Dólares');
                $table->decimal('tipo_de_cambio', 9, 4)->default(1.0000);
                $table->string('ruc', 20)->nullable();
                $table->string('tipo_documento', 10)->nullable();
                $table->string('serie', 20)->nullable();
                $table->string('numero', 20)->nullable();
                $table->string('guia', 50)->nullable();
                $table->decimal('percepcion', 9, 4)->default(0.0000);
                $table->enum('forma_de_pago', ['co', 'cr'])->default('co')->comment('co=Contado, cr=Crédito');
                $table->integer('numero_dias')->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->string('egreso_dinero_id', 191)->nullable();
                $table->string('despliegue_de_pago_id', 191)->nullable();
                $table->enum('estado', ['pendiente', 'en_proceso', 'completada', 'anulada'])->default('pendiente');
                $table->string('user_id', 191);
                $table->integer('almacen_id');
                $table->timestamps();

                $table->index('estado', 'idx_oc_estado');
                $table->index('fecha', 'idx_oc_fecha');
                $table->index('proveedor_id', 'idx_oc_proveedor');
                $table->index('requerimiento_id', 'idx_oc_req');
                $table->index('user_id', 'idx_oc_user');
                $table->index('almacen_id', 'idx_oc_almacen');
            });

            // Foreign keys
            if (Schema::hasTable('requerimientos_internos')) {
                Schema::table('ordenes_compra', function (Blueprint $table) {
                    $table->foreign('requerimiento_id')->references('id')->on('requerimientos_internos')->onDelete('set null')->onUpdate('cascade');
                });
            }

            if (Schema::hasTable('proveedor')) {
                Schema::table('ordenes_compra', function (Blueprint $table) {
                    $table->foreign('proveedor_id')->references('id')->on('proveedor')->onDelete('set null')->onUpdate('cascade');
                });
            }

            if (Schema::hasTable('user')) {
                Schema::table('ordenes_compra', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade')->onUpdate('cascade');
                });
            }

            if (Schema::hasTable('almacen')) {
                Schema::table('ordenes_compra', function (Blueprint $table) {
                    $table->foreign('almacen_id')->references('id')->on('almacen')->onDelete('cascade')->onUpdate('cascade');
                });
            }

            if (Schema::hasTable('egresodinero')) {
                Schema::table('ordenes_compra', function (Blueprint $table) {
                    $table->foreign('egreso_dinero_id')->references('id')->on('egresodinero')->onDelete('set null')->onUpdate('cascade');
                });
            }

            if (Schema::hasTable('desplieguedepago')) {
                Schema::table('ordenes_compra', function (Blueprint $table) {
                    $table->foreign('despliegue_de_pago_id')->references('id')->on('desplieguedepago')->onDelete('set null')->onUpdate('cascade');
                });
            }
        }

        // 2. Productos de una orden de compra
        if (!Schema::hasTable('orden_compra_productos')) {
            Schema::create('orden_compra_productos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_compra_id');
                $table->integer('producto_id');
                $table->string('codigo', 50)->nullable()->comment('Snapshot cod_producto');
                $table->string('nombre', 255)->nullable()->comment('Snapshot nombre producto');
                $table->string('marca', 100)->nullable()->comment('Snapshot marca');
                $table->string('unidad', 50)->nullable()->comment('Snapshot unidad medida');
                $table->decimal('cantidad', 9, 3);
                $table->decimal('precio', 12, 4)->comment('Precio unitario');
                $table->decimal('subtotal', 12, 2)->comment('cantidad × precio');
                $table->decimal('flete', 9, 2)->default(0.00);
                $table->date('vencimiento')->nullable();
                $table->string('lote', 100)->nullable();
                $table->timestamp('created_at')->useCurrent()->nullable();

                $table->index('orden_compra_id', 'idx_ocp_orden');
                $table->index('producto_id', 'idx_ocp_prod');

                $table->foreign('orden_compra_id')->references('id')->on('ordenes_compra')->onDelete('cascade')->onUpdate('cascade');
            });

            if (Schema::hasTable('producto')) {
                Schema::table('orden_compra_productos', function (Blueprint $table) {
                    $table->foreign('producto_id')->references('id')->on('producto')->onDelete('cascade')->onUpdate('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_compra_productos');
        Schema::dropIfExists('ordenes_compra');
    }
};
