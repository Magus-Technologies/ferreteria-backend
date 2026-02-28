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
        Schema::create('abonos_deuda_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deuda_personal_id')->constrained('deuda_personals')->onDelete('cascade');
            $table->decimal('monto', 10, 2);
            $table->unsignedInteger('metodo_pago_id')->nullable(); // Sin foreign key por incompatibilidad de tipos
            $table->string('numero_operacion', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('saldo_anterior', 10, 2);
            $table->decimal('saldo_despues', 10, 2);
            $table->foreignId('registrado_por_user_id')->constrained('users')->onDelete('restrict');
            $table->timestamp('fecha_abono');
            $table->timestamps();
            
            $table->index(['deuda_personal_id', 'fecha_abono']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abonos_deuda_personal');
    }
};
