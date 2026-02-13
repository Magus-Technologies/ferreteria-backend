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
        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->string('numero_letra')->nullable()->after('monto');
            $table->string('numero_operacion')->nullable()->after('numero_letra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->dropColumn(['numero_letra', 'numero_operacion']);
        });
    }
};
