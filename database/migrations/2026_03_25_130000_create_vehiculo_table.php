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
        Schema::create('vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191); // Nombre descriptivo: "MOTO ROJA", "CAMION AZUL"
            $table->string('tipo', 50); // MOTO, CAMION, FURGONETA, etc.
            $table->string('placa', 20)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        // Agregar vehiculo_id a entregaproducto
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->unsignedBigInteger('vehiculo_id')->nullable()->after('chofer_id');
            $table->foreign('vehiculo_id')->references('id')->on('vehiculo')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregaproducto', function (Blueprint $table) {
            $table->dropForeign(['vehiculo_id']);
            $table->dropColumn('vehiculo_id');
        });

        Schema::dropIfExists('vehiculo');
    }
};
