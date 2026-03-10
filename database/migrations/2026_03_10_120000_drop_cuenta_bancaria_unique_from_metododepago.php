<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina el unique constraint individual en cuenta_bancaria.
     * Se mantiene el composite unique (name, cuenta_bancaria) que permite
     * múltiples métodos de pago con cuenta_bancaria = 'SIN-CUENTA'
     * siempre que tengan nombres distintos.
     */
    public function up(): void
    {
        Schema::table('metododepago', function (Blueprint $table) {
            $table->dropUnique('metododepago_cuenta_bancaria_unique');
        });
    }

    public function down(): void
    {
        Schema::table('metododepago', function (Blueprint $table) {
            $table->unique('cuenta_bancaria', 'metododepago_cuenta_bancaria_unique');
        });
    }
};
