<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->string('tipo_precio', 20)->default('publico')->after('precio_sugerido')
                ->comment('Tipo de precio: publico, especial, minimo, ultimo');
            $table->decimal('descuento', 9, 4)->default(0)->after('tipo_precio')
                ->comment('Descuento fijo en soles');
        });
    }

    public function down(): void
    {
        Schema::table('paquete_producto', function (Blueprint $table) {
            $table->dropColumn(['tipo_precio', 'descuento']);
        });
    }
};
