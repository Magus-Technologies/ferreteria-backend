<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comprobantes_electronicos')) {
            return;
        }
        
        Schema::create('comprobantes_electronicos', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('venta_id', 191);
            $table->enum('tipo_comprobante', ['01', '03', '07', '08'])->comment('01=Factura, 03=Boleta, 07=NC, 08=ND');
            $table->string('serie', 4);
            $table->unsignedInteger('numero');
            $table->string('numero_comprobante_referencia', 50)->nullable()->comment('Serie-Número del comprobante que se modifica (para NC/ND)');
            $table->unsignedInteger('cliente_id');
            $table->string('ruc_cliente', 20)->nullable();
            $table->string('nombre_cliente', 255)->nullable();
            $table->string('direccion_cliente', 500)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0)->comment('Impuesto General a las Ventas');
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado_sunat', ['PENDIENTE', 'PROCESANDO', 'ACEPTADO', 'RECHAZADO', 'CANCELADO', 'BAJA'])->default('PENDIENTE');
            $table->longText('xml_generado')->nullable()->comment('XML generado por Greenter antes de firmar');
            $table->longText('xml_firmado')->nullable()->comment('XML firmado digitalmente');
            $table->longText('cdr_xml')->nullable()->comment('XML de respuesta de SUNAT');
            $table->longText('pdf_base64')->nullable()->comment('PDF en base64 para impresión');
            $table->string('codigo_error_sunat', 50)->nullable();
            $table->text('mensaje_error_sunat')->nullable();
            $table->dateTime('fecha_envio_sunat')->nullable();
            $table->dateTime('fecha_aceptacion_sunat')->nullable();
            $table->string('numero_ticket_sunat', 50)->nullable()->comment('Ticket de SUNAT para consultas');
            $table->string('resumen_ticket', 500)->nullable();
            $table->string('hash_cdr', 100)->nullable();
            $table->string('razon_social_empresa', 191)->nullable();
            $table->string('ruc_empresa', 20)->nullable();
            $table->unsignedBigInteger('numero_lote_envio')->nullable();
            $table->integer('intentos_envio')->default(0);
            $table->text('observaciones')->nullable();
            $table->string('user_id', 191);
            $table->timestamps();

            $table->unique('venta_id', 'uq_venta_id');
            $table->index(['tipo_comprobante', 'estado_sunat'], 'idx_tipo_comprobante_estado');
            $table->index(['serie', 'numero'], 'idx_serie_numero');
            $table->index('cliente_id', 'idx_cliente_id');
            $table->index('fecha_envio_sunat', 'idx_fecha_envio');
            $table->index('user_id', 'idx_user_id');
        });

        // Agregar foreign keys solo si las tablas existen
        if (Schema::hasTable('venta')) {
            Schema::table('comprobantes_electronicos', function (Blueprint $table) {
                $table->foreign('venta_id')->references('id')->on('venta')->onDelete('cascade')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('cliente')) {
            Schema::table('comprobantes_electronicos', function (Blueprint $table) {
                $table->foreign('cliente_id')->references('id')->on('cliente')->onUpdate('cascade');
            });
        }
        
        if (Schema::hasTable('user')) {
            Schema::table('comprobantes_electronicos', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('user')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes_electronicos');
    }
};
