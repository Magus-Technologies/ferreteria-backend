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
        Schema::create('ingresos_extras', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->decimal('monto', 10, 2);
            $table->text('concepto');
            $table->enum('estado', ['pendiente', 'aprobado', 'anulado'])->default('pendiente');

            $table->string('user_id', 191); // Quien registró el ingreso
            $table->string('supervisor_id', 191)->nullable(); // Quién lo autorizó
            $table->string('despliegue_pago_id', 191)->nullable(); // Yape, Efectivo, etc.

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();
            $table->foreign('supervisor_id')->references('id')->on('user')->nullOnDelete();
            $table->foreign('despliegue_pago_id')->references('id')->on('desplieguedepago')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingresos_extras');
    }
};
