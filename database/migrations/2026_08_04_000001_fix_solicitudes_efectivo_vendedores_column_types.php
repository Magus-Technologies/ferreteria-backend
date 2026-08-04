<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_efectivo_vendedores', function (Blueprint $table) {
            // user.id es varchar(191): las columnas de vendedores deben ser string, no bigint
            $table->dropForeign('fk_solicitud_solicitante');
            $table->dropForeign('fk_solicitud_prestamista');

            $table->string('vendedor_solicitante_id', 191)->change();
            $table->string('vendedor_prestamista_id', 191)->change();

            $table->foreign('vendedor_solicitante_id', 'fk_solicitud_solicitante')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('vendedor_prestamista_id', 'fk_solicitud_prestamista')->references('id')->on('user')->onDelete('cascade');
        });

        // Agregar columnas faltantes de sub-cajas (sub_cajas.id es int)
        Schema::table('solicitudes_efectivo_vendedores', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_efectivo_vendedores', 'sub_caja_destino_id')) {
                $table->integer('sub_caja_destino_id')->nullable()->comment('Caja Chica del solicitante donde se depositará el préstamo');
                $table->integer('sub_caja_origen_id')->nullable()->comment('Sub-caja del prestamista de donde saldrá el dinero');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_efectivo_vendedores', function (Blueprint $table) {
            $table->dropForeign('fk_solicitud_solicitante');
            $table->dropForeign('fk_solicitud_prestamista');

            $table->bigInteger('vendedor_solicitante_id')->unsigned()->change();
            $table->bigInteger('vendedor_prestamista_id')->unsigned()->change();

            $table->foreign('vendedor_solicitante_id', 'fk_solicitud_solicitante')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('vendedor_prestamista_id', 'fk_solicitud_prestamista')->references('id')->on('user')->onDelete('cascade');
        });

        Schema::table('solicitudes_efectivo_vendedores', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes_efectivo_vendedores', 'sub_caja_destino_id')) {
                $table->dropColumn(['sub_caja_destino_id', 'sub_caja_origen_id']);
            }
        });
    }
};
