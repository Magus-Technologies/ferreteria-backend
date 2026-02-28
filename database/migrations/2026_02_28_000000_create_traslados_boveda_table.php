<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traslados_boveda', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('apertura_cierre_caja_id', 255);
            $table->string('vendedor_id', 255);
            $table->string('supervisor_id', 255);
            $table->decimal('monto', 10, 2);
            $table->text('justificacion')->nullable();
            $table->timestamp('fecha_traslado')->useCurrent();
            $table->timestamps();

            $table->foreign('apertura_cierre_caja_id')->references('id')->on('apertura_cierre_caja')->onDelete('cascade');
            $table->foreign('vendedor_id')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('supervisor_id')->references('id')->on('user')->onDelete('cascade');

            $table->index('apertura_cierre_caja_id');
            $table->index('vendedor_id');
            $table->index('supervisor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traslados_boveda');
    }
};
