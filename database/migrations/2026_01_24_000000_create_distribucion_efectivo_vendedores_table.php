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
        Schema::create('distribucion_efectivo_vendedores', function (Blueprint $table) {
            $table->id();
            $table->string('apertura_cierre_caja_id');
            $table->string('user_id');
            $table->decimal('monto', 10, 2);
            $table->json('conteo_billetes_monedas')->nullable()->comment('Detalle del conteo de billetes y monedas');
            $table->timestamps();

            // Foreign keys
            $table->foreign('apertura_cierre_caja_id')
                ->references('id')
                ->on('apertura_cierre_caja')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // Indexes
            $table->index('apertura_cierre_caja_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribucion_efectivo_vendedores');
    }
};
