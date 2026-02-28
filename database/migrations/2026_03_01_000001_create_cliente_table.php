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
        Schema::create('cliente', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_cliente', ['p', 'e'])->default('p')->comment('p=persona, e=empresa');
            $table->string('numero_documento', 191)->unique('Cliente_numero_documento_key');
            $table->string('nombres', 255)->nullable();
            $table->string('apellidos', 255)->nullable();
            $table->string('razon_social', 191)->nullable();
            $table->string('telefono', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->boolean('estado')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
