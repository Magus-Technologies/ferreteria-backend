<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('termino_empresa', function (Blueprint $table) {
            $table->id();
            $table->integer('empresa_id');
            $table->enum('tipo', [
                'comprobantes_ventas',
                'letras_cambio',
                'guias_remision',
                'cotizaciones',
                'ordenes_compras',
            ]);
            $table->text('contenido')->nullable();
            $table->timestamps();

            $table->index('empresa_id');
            $table->unique(['empresa_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termino_empresa');
    }
};
