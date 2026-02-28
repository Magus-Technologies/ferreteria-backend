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
        Schema::create('direcciones_cliente', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id')->comment('ID del cliente (FK a tabla cliente)');
            $table->enum('tipo', ['D1', 'D2', 'D3', 'D4'])->comment('Tipo de dirección (D1=Principal, D2-D4=Alternativas)');
            $table->string('direccion', 500)->comment('Dirección completa');
            $table->decimal('latitud', 10, 8)->nullable()->comment('Latitud GPS (-90 a 90)');
            $table->decimal('longitud', 11, 8)->nullable()->comment('Longitud GPS (-180 a 180)');
            $table->boolean('es_principal')->default(false)->nullable()->comment('Indica si es la dirección principal');
            $table->timestamps();

            $table->unique(['cliente_id', 'tipo'], 'unique_cliente_tipo');
            $table->index('cliente_id', 'idx_cliente_id');
            $table->index('es_principal', 'idx_es_principal');
            $table->index('tipo', 'idx_tipo');
            $table->index(['latitud', 'longitud'], 'idx_coordenadas');

            $table->foreign('cliente_id', 'fk_direcciones_cliente_cliente')
                ->references('id')->on('cliente')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direcciones_cliente');
    }
};
