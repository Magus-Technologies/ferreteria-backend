<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transferencias_efectivo_vendedores', function (Blueprint $table) {
            // user.id es varchar(191): las columnas de vendedores deben ser string, no bigint
            $table->dropForeign('fk_transf_origen');
            $table->dropForeign('fk_transf_destino');

            $table->string('vendedor_origen_id', 191)->change();
            $table->string('vendedor_destino_id', 191)->change();

            $table->foreign('vendedor_origen_id', 'fk_transf_origen')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('vendedor_destino_id', 'fk_transf_destino')->references('id')->on('user')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('transferencias_efectivo_vendedores', function (Blueprint $table) {
            $table->dropForeign('fk_transf_origen');
            $table->dropForeign('fk_transf_destino');

            $table->bigInteger('vendedor_origen_id')->unsigned()->change();
            $table->bigInteger('vendedor_destino_id')->unsigned()->change();

            $table->foreign('vendedor_origen_id', 'fk_transf_origen')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('vendedor_destino_id', 'fk_transf_destino')->references('id')->on('user')->onDelete('cascade');
        });
    }
};
