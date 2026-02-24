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
        Schema::create('deuda_personals', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 191); // Usuario que tiene la deuda (el vendedor)
            $table->ulid('arqueo_diario_id')->nullable(); // El arqueo que generó la deuda, si aplica
            $table->decimal('monto', 10, 2); // Monto de la deuda (siempre positivo)
            $table->enum('estado', ['pendiente', 'pagado', 'anulado'])->default('pendiente');
            $table->text('observaciones')->nullable(); // Detalles sobre cuándo o por qué se generó
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();
            $table->foreign('arqueo_diario_id')->references('id')->on('arqueos_diarios')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deuda_personals');
    }
};
